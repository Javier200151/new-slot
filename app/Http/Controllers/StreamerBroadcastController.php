<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use App\Services\StreamEmbedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class StreamerBroadcastController extends Controller
{
    public function update(
        Request $request,
        StreamEmbedService $embedService
    ): RedirectResponse {
        $streamer = $request
            ->user()
            ?->streamer;

        abort_unless(
            $streamer
            && $streamer->enable,
            403
        );

        $validated = $request->validate([
            'event_id' => [
                'required',
                'integer',
                'exists:events,id',
            ],

            'platform' => [
                'required',
                Rule::in([
                    'twitch',
                    'youtube',
                ]),
            ],

            'twitch_username' => [
                'nullable',
                'required_if:platform,twitch',
                'string',
                'max:100',
                'regex:/^[A-Za-z0-9_]+$/',
            ],

            'youtube_url' => [
                'nullable',
                'required_if:platform,youtube',
                'url',
                'max:500',
            ],

            'title' => [
                'nullable',
                'string',
                'max:255',
            ],
        ], [
            'twitch_username.required_if' =>
                'Introduce tu nombre de usuario de Twitch.',

            'twitch_username.regex' =>
                'El usuario de Twitch solo puede contener letras, números y guiones bajos.',

            'youtube_url.required_if' =>
                'Introduce el enlace del directo de YouTube.',

            'youtube_url.url' =>
                'Introduce una URL válida de YouTube.',
        ]);

        /*
        |--------------------------------------------------------------------------
        | Validar evento
        |--------------------------------------------------------------------------
        |
        | No confiamos únicamente en exists:events,id porque esa regla no aplica
        | automáticamente el scope de SoftDeletes ni comprueba el estado funcional
        | del evento.
        |
        */

        $event = Event::query()
            ->with('eventStatus')
            ->find($validated['event_id']);

        if (! $event) {
            throw ValidationException::withMessages([
                'event_id' => [
                    'El evento seleccionado no existe o ya no está disponible.',
                ],
            ]);
        }

        if ($event->eventStatus?->name !== 'ACTIVO') {
            throw ValidationException::withMessages([
                'event_id' => [
                    'Solo puedes iniciar una emisión en un evento ACTIVO.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Ventana temporal permitida
        |--------------------------------------------------------------------------
        |
        | Aplicamos exactamente el mismo criterio que en /directos:
        |
        | - hasta 12 horas después de la hora de inicio del evento;
        | - eventos programados como máximo a 30 días.
        |
        | De este modo no se puede manipular event_id para iniciar una
        | retransmisión sobre un evento antiguo o demasiado lejano.
        |
        */

        if (! $event->date) {
            throw ValidationException::withMessages([
                'event_id' => [
                    'El evento seleccionado no tiene una fecha válida.',
                ],
            ]);
        }

        $eventDate =
            Carbon::parse($event->date);

        $now = now();

        $windowStart =
            $now->copy()->subHours(12);

        $windowEnd =
            $now->copy()->addDays(30);

        if (
            $eventDate->lt($windowStart)
            || $eventDate->gt($windowEnd)
        ) {
            throw ValidationException::withMessages([
                'event_id' => [
                    'Solo puedes iniciar una emisión desde '
                    . '12 horas antes del evento y hasta eventos '
                    . 'programados dentro de los próximos 30 días.',
                ],
            ]);
        }

        if ($validated['platform'] === 'twitch') {

            $twitchUsername = ltrim(
                trim($validated['twitch_username']),
                '@'
            );

            $streamUrl =
                'https://www.twitch.tv/'
                . $twitchUsername;

        } else {

            $streamUrl =
                $validated['youtube_url'];
        }

        if (
            ! $embedService->supports(
                $validated['platform'],
                $streamUrl
            )
        ) {
            throw ValidationException::withMessages([
                'stream_url' => [
                    'La URL no corresponde con '
                    . 'la plataforma seleccionada.',
                ],
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Activar emisión
        |--------------------------------------------------------------------------
        |
        | Bloqueamos la fila del streamer durante toda la operación.
        | Así dos peticiones simultáneas del mismo streamer no pueden
        | dejar dos emisiones activas a la vez.
        |
        */

        DB::transaction(function () use (
            $streamer,
            $validated,
            $streamUrl
        ): void {

            /*
            * Bloqueamos al streamer.
            */
            $lockedStreamer = $streamer
                ->newQuery()
                ->whereKey($streamer->id)
                ->lockForUpdate()
                ->firstOrFail();


            /*
            * Desactivamos cualquier otra emisión
            * activa del mismo streamer.
            */
            $activeStreams = Stream::query()
                ->where(
                    'streamer_id',
                    $lockedStreamer->id
                )
                ->where('enabled', true)
                ->where(
                    'event_id',
                    '!=',
                    $validated['event_id']
                )
                ->get();

            foreach ($activeStreams as $activeStream) {
                $activeStream->update([
                    'enabled' => false,
                    'ended_at' => now(),
                ]);
            }


            /*
            * Recuperamos o creamos la emisión
            * correspondiente al evento seleccionado.
            */
            $stream = Stream::query()
                ->firstOrNew([
                    'event_id' =>
                        $validated['event_id'],

                    'streamer_id' =>
                        $lockedStreamer->id,
                ]);

            $wasEnabled =
                (bool) $stream->enabled;

            $stream->fill([
                'platform' =>
                    $validated['platform'],

                'stream_url' =>
                    $streamUrl,

                'title' =>
                    $validated['title'] ?? null,

                'enabled' => true,

                'ended_at' => null,
            ]);

            if (! $wasEnabled) {
                $stream->started_at =
                    now();
            }

            $stream->save();
        });


        /*
        |--------------------------------------------------------------------------
        | Invalidar caché pública
        |--------------------------------------------------------------------------
        |
        | /directos/estado se cachea durante unos segundos.
        | Al modificar una emisión forzamos que la siguiente consulta
        | vuelva a obtener el estado real desde base de datos.
        |
        */

        Cache::forget(
            'public_streams_status'
        );

        return redirect()
            ->route('streams.index')
            ->with(
                'success',
                'Tu emisión está ahora visible.'
            );
    }

    public function destroy(
        Request $request,
        Event $event
    ): RedirectResponse {
        $streamer = $request
            ->user()
            ?->streamer;

        abort_unless(
            $streamer
            && $streamer->enable,
            403
        );

        $stream = Stream::query()
            ->where(
                'event_id',
                $event->id
            )
            ->where(
                'streamer_id',
                $streamer->id
            )
            ->firstOrFail();

        $stream->update([
            'enabled' => false,
            'ended_at' => now(),
        ]);

        Cache::forget(
            'public_streams_status'
        );

        return redirect()
            ->route('streams.index')
            ->with(
                'success',
                'Tu emisión ha sido desactivada.'
            );
    }
}