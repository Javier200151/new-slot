<?php

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\Stream;
use App\Models\Streamer;
use App\Services\PublicLiveStreamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\View\View;

class PublicStreamerController extends Controller
{
    public function status(PublicLiveStreamService $liveStreams): JsonResponse
    {
        $payload = Cache::remember(
            'public_streams_status',
            now()->addSeconds(5),
            fn (): array => $liveStreams->statusPayload(
                $liveStreams->active(),
            ),
        );

        return response()->json($payload);
    }

    public function index(
        Request $request,
        PublicLiveStreamService $liveStreams,
    ): View {
        /*
         * Todos los streamers habilitados.
         */
        $streamers = Streamer::query()
            ->where('enable', true)
            ->with('user')
            ->get()
            ->sortBy(
                fn (Streamer $streamer): string => strtolower(
                    $streamer->user?->nick ?? '',
                ),
            )
            ->values();

        /*
         * Combina directos detectados automáticamente en Twitch/YouTube con
         * las emisiones publicadas manualmente como respaldo.
         */
        $activeStreams = $liveStreams->active($streamers);

        /*
         * Streamer asociado al usuario actual.
         */
        $myStreamer = $request->user()?->streamer;

        /*
         * Eventos disponibles para la publicación manual de respaldo.
         *
         * Desde 12 horas antes hasta 30 días hacia adelante.
         */
        $availableEvents = collect();

        if ($myStreamer && $myStreamer->enable) {
            $availableEvents = Event::query()
                ->whereHas(
                    'eventStatus',
                    fn ($query) => $query->where('name', 'ACTIVO'),
                )
                ->where('date', '>=', now()->subHours(12))
                ->where('date', '<=', now()->addDays(30))
                ->orderBy('date')
                ->get();
        }

        /*
         * Emisión activada manualmente por el streamer actual.
         */
        $myActiveStream = null;

        if ($myStreamer && $myStreamer->enable) {
            $myActiveStream = Stream::query()
                ->where('streamer_id', $myStreamer->id)
                ->where('enabled', true)
                ->with('event')
                ->latest('started_at')
                ->first();
        }

        $myAutomaticStreams = collect();

        if ($myStreamer && $myStreamer->enable) {
            $myAutomaticStreams = $activeStreams
                ->filter(
                    fn (Stream $stream): bool => (int) $stream->streamer_id === (int) $myStreamer->id
                        && $stream->getAttribute('discovery_source') === 'automatic',
                )
                ->values();
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
                'myAutomaticStreams',
                'availableEvents',
            ),
        );
    }
}
