<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Stream;
use App\Models\Streamer;
use Illuminate\Support\Collection;

class PublicLiveStreamService
{
    public function __construct(
        private readonly AutomaticLiveStreamService $automatic,
        private readonly StreamEmbedService $embedService,
    ) {
    }

    /**
     * @param  Collection<int, Streamer>|null  $streamers
     * @return Collection<int, Stream>
     */
    public function active(?Collection $streamers = null, bool $forceAutomatic = false): Collection
    {
        $streamers ??= Streamer::query()
            ->where('enable', true)
            ->with('user')
            ->orderBy('id')
            ->get();

        $streamers = $streamers
            ->filter(fn (Streamer $streamer): bool => (bool) $streamer->enable)
            ->values();

        $streamersById = $streamers->keyBy(fn (Streamer $streamer): int => (int) $streamer->id);

        $manual = $this->manualStreams()
            ->map(function (Stream $stream): Stream {
                $stream->setAttribute('public_key', 'manual-' . $stream->id);
                $stream->setAttribute('discovery_source', 'manual');

                return $stream;
            });

        $automaticRecords = $this->automatic->discover($streamers, $forceAutomatic);
        $automaticKeys = $automaticRecords
            ->map(fn (array $record): string => $this->platformKey(
                (int) ($record['streamer_id'] ?? 0),
                (string) ($record['platform'] ?? ''),
            ))
            ->flip();

        $automatic = $automaticRecords
            ->map(function (array $record) use ($streamersById, $manual): ?Stream {
                $streamerId = (int) ($record['streamer_id'] ?? 0);
                $platform = (string) ($record['platform'] ?? '');
                $streamer = $streamersById->get($streamerId);

                if (! $streamer || ! in_array($platform, ['twitch', 'youtube'], true)) {
                    return null;
                }

                $manualMatch = $manual->first(
                    fn (Stream $stream): bool => (int) $stream->streamer_id === $streamerId
                        && (string) $stream->platform === $platform,
                );

                $event = $manualMatch?->event ?: $this->matchEvent($streamer);

                $stream = new Stream();
                $stream->forceFill([
                    'event_id' => $event?->id,
                    'streamer_id' => $streamerId,
                    'platform' => $platform,
                    'stream_url' => (string) ($record['url'] ?? ''),
                    'enabled' => true,
                    'title' => $record['title'] ?? null,
                    'started_at' => $record['started_at'] ?? null,
                    'ended_at' => null,
                ]);

                $stream->setAttribute(
                    'public_key',
                    'auto-' . $platform . '-' . $streamerId . '-' . ($record['external_id'] ?? 'live'),
                );
                $stream->setAttribute('discovery_source', 'automatic');
                $stream->setRelation('streamer', $streamer);

                if ($event) {
                    $stream->setRelation('event', $event);
                }

                return $stream;
            })
            ->filter()
            ->values();

        $manualFallback = $manual->reject(
            fn (Stream $stream): bool => $automaticKeys->has(
                $this->platformKey((int) $stream->streamer_id, (string) $stream->platform),
            ),
        );

        return $automatic
            ->concat($manualFallback)
            ->map(fn (Stream $stream): Stream => $this->enrich($stream))
            ->sortByDesc(fn (Stream $stream): int => $stream->started_at?->timestamp ?? 0)
            ->values();
    }

    /**
     * @param  Collection<int, Stream>  $streams
     */
    public function statusPayload(Collection $streams): array
    {
        return [
            'automatic_detection' => $this->automatic->enabled(),
            'streams' => $streams
                ->map(fn (Stream $stream): array => [
                    'id' => (string) ($stream->getAttribute('public_key') ?: ('manual-' . $stream->id)),
                    'event_id' => $stream->event_id,
                    'streamer_id' => $stream->streamer_id,
                    'platform' => $stream->platform,
                    'source' => $stream->getAttribute('discovery_source') ?: 'manual',
                    'updated_at' => $stream->started_at?->timestamp
                        ?? $stream->updated_at?->timestamp,
                ])
                ->values()
                ->all(),
        ];
    }

    /**
     * @return Collection<int, Stream>
     */
    private function manualStreams(): Collection
    {
        return Stream::query()
            ->where('enabled', true)
            ->whereHas('streamer', fn ($query) => $query->where('enable', true))
            ->whereHas('event', function ($query): void {
                $query
                    ->whereHas(
                        'eventStatus',
                        fn ($statusQuery) => $statusQuery->where('name', 'ACTIVO'),
                    )
                    ->where('date', '>=', now()->subHours(12))
                    ->where('date', '<=', now()->addDays(30));
            })
            ->with([
                'event.slots',
                'streamer.user',
            ])
            ->orderByDesc('started_at')
            ->get();
    }

    private function matchEvent(Streamer $streamer): ?Event
    {
        if (! $streamer->user_id) {
            return null;
        }

        $before = max(1, (int) config('newslot.streaming.event_match_before_hours', 12));
        $after = max(1, (int) config('newslot.streaming.event_match_after_hours', 12));
        $now = now();

        return Event::query()
            ->whereHas(
                'eventStatus',
                fn ($query) => $query->where('name', 'ACTIVO'),
            )
            ->whereBetween('date', [
                $now->copy()->subHours($before),
                $now->copy()->addHours($after),
            ])
            ->whereHas(
                'slots',
                fn ($query) => $query->where('user_id', $streamer->user_id),
            )
            ->with('slots')
            ->get()
            ->sortBy(fn (Event $event): int => abs($event->date->diffInSeconds($now, false)))
            ->first();
    }

    private function enrich(Stream $stream): Stream
    {
        $stream->setAttribute('embed_url', $this->embedService->embedUrl($stream));

        $assignment = null;

        if ($stream->event && $stream->streamer) {
            if (! $stream->event->relationLoaded('slots')) {
                $stream->event->load('slots');
            }

            $assignment = $stream->event->slots->first(
                fn ($slot): bool => (int) $slot->user_id === (int) $stream->streamer->user_id,
            );
        }

        $stream->setAttribute('orbat_slot_name', $assignment?->name);
        $stream->setAttribute('orbat_group_name', $assignment?->slot_group);

        return $stream;
    }

    private function platformKey(int $streamerId, string $platform): string
    {
        return $streamerId . ':' . strtolower($platform);
    }
}
