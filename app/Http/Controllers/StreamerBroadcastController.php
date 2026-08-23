<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use App\Services\StreamEmbedService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

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
         * Solo permitimos una emisión activa
         * simultánea por streamer.
         */
        Stream::query()
            ->where(
                'streamer_id',
                $streamer->id
            )
            ->where('enabled', true)
            ->where(
                'event_id',
                '!=',
                $validated['event_id']
            )
            ->update([
                'enabled' => false,
                'ended_at' => now(),
            ]);

        $stream = Stream::query()
            ->firstOrNew([
                'event_id' =>
                    $validated['event_id'],

                'streamer_id' =>
                    $streamer->id,
            ]);

        $wasEnabled =
            (bool) $stream->enabled;

        $stream->fill([
            'platform' =>
                $validated['platform'],

            'stream_url' => $streamUrl,

            'title' =>
                $validated['title'] ?? null,

            'enabled' => true,

            'ended_at' => null,
        ]);

        if (! $wasEnabled) {
            $stream->started_at = now();
        }

        $stream->save();

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

        return redirect()
            ->route('streams.index')
            ->with(
                'success',
                'Tu emisión ha sido desactivada.'
            );
    }
}