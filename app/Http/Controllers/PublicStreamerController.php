<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use App\Models\Streamer;
use App\Services\StreamEmbedService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicStreamerController extends Controller
{
    public function index(
        Request $request,
        StreamEmbedService $embedService
    ): View {
        /*
         * Todos los streamers habilitados.
         */
        $streamers = Streamer::query()
            ->where('enable', true)
            ->with('user')
            ->get()
            ->sortBy(
                fn (Streamer $streamer): string =>
                    strtolower(
                        $streamer->user?->nick ?? ''
                    )
            )
            ->values();

        /*
         * Emisiones activadas manualmente.
         */
        $activeStreams = Stream::query()
            ->where('enabled', true)
            ->whereHas(
                'streamer',
                fn ($query) =>
                    $query->where(
                        'enable',
                        true
                    )
            )
            ->with([
                'event',
                'streamer.user',
            ])
            ->orderByDesc('started_at')
            ->get()
            ->map(
                function (
                    Stream $stream
                ) use ($embedService): Stream {
                    $stream->setAttribute(
                        'embed_url',
                        $embedService->embedUrl(
                            $stream
                        )
                    );

                    return $stream;
                }
            );

        /*
         * Streamer asociado al usuario actual.
         */
        $myStreamer = $request
            ->user()
            ?->streamer;

        /*
         * Eventos disponibles para publicar.
         *
         * Desde 12 horas antes hasta 30 días
         * hacia adelante.
         */
        $availableEvents = collect();

        if (
            $myStreamer
            && $myStreamer->enable
        ) {
            $availableEvents = Event::query()
                ->where(
                    'date',
                    '>=',
                    now()->subHours(12)
                )
                ->where(
                    'date',
                    '<=',
                    now()->addDays(30)
                )
                ->orderBy('date')
                ->get();
        }

        /*
         * Emisión activa del streamer actual.
         */
        $myActiveStream = null;

        if (
            $myStreamer
            && $myStreamer->enable
        ) {
            $myActiveStream = Stream::query()
                ->where(
                    'streamer_id',
                    $myStreamer->id
                )
                ->where(
                    'enabled',
                    true
                )
                ->with('event')
                ->latest('started_at')
                ->first();
        }

        $activeStreamerIds = $activeStreams
            ->pluck('streamer_id')
            ->unique();

        return view(
            'streams.index',
            compact(
                'streamers',
                'activeStreams',
                'activeStreamerIds',
                'myStreamer',
                'myActiveStream',
                'availableEvents',
            )
        );
    }
}