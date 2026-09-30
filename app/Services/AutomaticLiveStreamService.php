<?php

namespace App\Services;

use App\Models\Streamer;
use App\Support\StreamingChannelParser;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class AutomaticLiveStreamService
{
    public function enabled(): bool
    {
        return (bool) config('newslot.streaming.automatic_live', true);
    }

    /**
     * @param  Collection<int, Streamer>  $streamers
     * @return Collection<int, array<string, mixed>>
     */
    public function discover(Collection $streamers, bool $force = false): Collection
    {
        if (! $this->enabled()) {
            return collect();
        }

        $streamers = $streamers
            ->filter(fn (Streamer $streamer): bool => (bool) $streamer->enable)
            ->values();

        if ($streamers->isEmpty()) {
            return collect();
        }

        $fingerprint = sha1((string) json_encode(
            $streamers->map(fn (Streamer $streamer): array => [
                'id' => $streamer->id,
                'updated_at' => $streamer->updated_at?->timestamp,
                'twitch_channel' => $streamer->twitch_channel,
                'twitch_user_id' => $streamer->twitch_user_id,
                'youtube_channel' => $streamer->youtube_channel,
                'youtube_channel_id' => $streamer->youtube_channel_id,
            ])->all(),
            JSON_UNESCAPED_SLASHES,
        ));

        $cacheKey = 'streams.automatic-live.v1.' . $fingerprint;

        if ($force) {
            Cache::forget($cacheKey);
        }

        $seconds = max(15, (int) config('newslot.streaming.live_cache_seconds', 60));

        $cached = Cache::remember(
            $cacheKey,
            now()->addSeconds($seconds),
            fn (): array => $this->discoverFresh($streamers)
                ->map(function (array $stream): array {
                    $startedAt = $stream['started_at'] ?? null;

                    if ($startedAt instanceof Carbon) {
                        $stream['started_at'] = $startedAt->toIso8601String();
                    }

                    return $stream;
                })
                ->values()
                ->all(),
        );

        return collect(is_array($cached) ? $cached : [])
            ->map(function (array $stream): array {
                $startedAt = $stream['started_at'] ?? null;

                if (is_string($startedAt) && $startedAt !== '') {
                    try {
                        $stream['started_at'] = Carbon::parse($startedAt);
                    } catch (Throwable) {
                        $stream['started_at'] = null;
                    }
                }

                return $stream;
            });
    }

    /**
     * @param  Collection<int, Streamer>  $streamers
     * @return Collection<int, array<string, mixed>>
     */
    private function discoverFresh(Collection $streamers): Collection
    {
        $live = collect();

        foreach ($this->twitchLiveStreams($streamers) as $stream) {
            $live->push($stream);
        }

        foreach ($streamers as $streamer) {
            $youtube = $this->youtubeLiveStream($streamer);

            if ($youtube) {
                $live->push($youtube);
            }
        }

        return $live
            ->unique(fn (array $stream): string => implode(':', [
                $stream['streamer_id'] ?? '',
                $stream['platform'] ?? '',
                $stream['external_id'] ?? '',
            ]))
            ->values();
    }

    /**
     * @param  Collection<int, Streamer>  $streamers
     * @return array<int, array<string, mixed>>
     */
    private function twitchLiveStreams(Collection $streamers): array
    {
        $clientId = trim((string) config('services.twitch.client_id'));
        $clientSecret = trim((string) config('services.twitch.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            return [];
        }

        try {
            $token = $this->twitchToken($clientId, $clientSecret);

            if (! $token) {
                return [];
            }

            $byUserId = [];

            foreach ($streamers as $streamer) {
                if (! filled($streamer->twitch_channel) && ! filled($streamer->twitch_user_id)) {
                    continue;
                }

                $userId = trim((string) $streamer->twitch_user_id);

                if ($userId === '') {
                    $login = StreamingChannelParser::twitchLogin($streamer->twitch_channel);
                    $userId = (string) ($this->resolveTwitchUserId(
                        $streamer,
                        $login,
                        $clientId,
                        $token,
                    ) ?? '');
                }

                if ($userId !== '') {
                    $byUserId[$userId] = $streamer;
                }
            }

            if ($byUserId === []) {
                return [];
            }

            $query = implode('&', array_map(
                fn (string $id): string => 'user_id=' . rawurlencode($id),
                array_keys($byUserId),
            ));

            $response = Http::timeout(6)
                ->retry(1, 150)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get('https://api.twitch.tv/helix/streams?' . $query);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('data', []))
                ->map(function (array $item) use ($byUserId): ?array {
                    $userId = (string) ($item['user_id'] ?? '');
                    $streamer = $byUserId[$userId] ?? null;

                    if (! $streamer) {
                        return null;
                    }

                    $login = (string) ($item['user_login'] ?? '');
                    $url = trim((string) $streamer->twitch_channel);

                    if ($url === '' && $login !== '') {
                        $url = 'https://www.twitch.tv/' . $login;
                    }

                    return [
                        'streamer_id' => $streamer->id,
                        'platform' => 'twitch',
                        'external_id' => (string) ($item['id'] ?? $userId),
                        'url' => $url,
                        'title' => trim((string) ($item['title'] ?? '')) ?: 'Directo de Twitch',
                        'started_at' => ! empty($item['started_at'])
                            ? Carbon::parse($item['started_at'])
                            : null,
                    ];
                })
                ->filter()
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function youtubeLiveStream(Streamer $streamer): ?array
    {
        if (! filled($streamer->youtube_channel) && ! filled($streamer->youtube_channel_id)) {
            return null;
        }

        $channelId = $this->resolveYoutubeChannelId($streamer);

        if (! $channelId) {
            return null;
        }

        try {
            $response = Http::timeout(6)
                ->retry(1, 150)
                ->withHeaders([
                    'User-Agent' => 'Mozilla/5.0 (compatible; SquadAlpha-NewSlot/1.0)',
                    'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                ])
                ->get('https://www.youtube.com/channel/' . rawurlencode($channelId) . '/live');

            if (! $response->successful()) {
                return null;
            }

            $live = StreamingChannelParser::youtubeLiveFromHtml($response->body());

            if (! $live) {
                return null;
            }

            return [
                'streamer_id' => $streamer->id,
                'platform' => 'youtube',
                'external_id' => $live['id'],
                'url' => 'https://www.youtube.com/watch?v=' . $live['id'],
                'title' => $live['title'] ?: 'Directo de YouTube',
                'started_at' => filled($live['started_at'])
                    ? Carbon::parse($live['started_at'])
                    : null,
            ];
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveYoutubeChannelId(Streamer $streamer): ?string
    {
        $saved = trim((string) $streamer->youtube_channel_id);

        if ($this->validYoutubeChannelId($saved)) {
            return $saved;
        }

        $url = trim((string) $streamer->youtube_channel);

        if ($url === '') {
            return null;
        }

        $direct = StreamingChannelParser::youtubeChannelIdFromUrl($url);

        if ($direct) {
            $this->persistYoutubeChannelId($streamer, $direct);

            return $direct;
        }

        $resolved = Cache::remember(
            'streams.youtube-channel-id.' . $streamer->id . '.' . sha1($url),
            now()->addDays(7),
            function () use ($url): ?string {
                try {
                    $response = Http::timeout(6)
                        ->retry(1, 150)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (compatible; SquadAlpha-NewSlot/1.0)',
                            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                        ])
                        ->get($url);

                    if (! $response->successful()) {
                        return null;
                    }

                    return StreamingChannelParser::youtubeChannelIdFromHtml($response->body());
                } catch (Throwable) {
                    return null;
                }
            },
        );

        if ($resolved && $this->validYoutubeChannelId($resolved)) {
            $this->persistYoutubeChannelId($streamer, $resolved);

            return $resolved;
        }

        return null;
    }

    private function twitchToken(string $clientId, string $clientSecret): ?string
    {
        $cacheKey = 'streams.twitch-app-token.' . sha1($clientId);
        $cached = Cache::get($cacheKey);

        if (is_string($cached) && $cached !== '') {
            return $cached;
        }

        try {
            $response = Http::asForm()
                ->timeout(6)
                ->retry(1, 150)
                ->post('https://id.twitch.tv/oauth2/token', [
                    'client_id' => $clientId,
                    'client_secret' => $clientSecret,
                    'grant_type' => 'client_credentials',
                ]);

            if (! $response->successful()) {
                return null;
            }

            $token = trim((string) $response->json('access_token'));
            $expiresIn = max(600, (int) $response->json('expires_in', 43200));

            if ($token === '') {
                return null;
            }

            Cache::put(
                $cacheKey,
                $token,
                now()->addSeconds(max(300, $expiresIn - 300)),
            );

            return $token;
        } catch (Throwable) {
            return null;
        }
    }

    private function resolveTwitchUserId(
        Streamer $streamer,
        ?string $login,
        string $clientId,
        string $token,
    ): ?string {
        if (! $login) {
            return null;
        }

        $resolved = Cache::remember(
            'streams.twitch-user-id.' . $streamer->id . '.' . strtolower($login),
            now()->addDays(7),
            function () use ($login, $clientId, $token): ?string {
                try {
                    $response = Http::timeout(6)
                        ->retry(1, 150)
                        ->withHeaders([
                            'Client-Id' => $clientId,
                            'Authorization' => 'Bearer ' . $token,
                        ])
                        ->get('https://api.twitch.tv/helix/users', [
                            'login' => $login,
                        ]);

                    if (! $response->successful()) {
                        return null;
                    }

                    $id = data_get($response->json(), 'data.0.id');

                    return is_scalar($id) ? (string) $id : null;
                } catch (Throwable) {
                    return null;
                }
            },
        );

        if ($resolved) {
            $this->persistTwitchUserId($streamer, $resolved);
        }

        return $resolved;
    }

    private function persistYoutubeChannelId(Streamer $streamer, string $channelId): void
    {
        if (! $streamer->exists || $streamer->youtube_channel_id === $channelId) {
            return;
        }

        $streamer->forceFill(['youtube_channel_id' => $channelId]);
        $streamer->saveQuietly();
    }

    private function persistTwitchUserId(Streamer $streamer, string $userId): void
    {
        if (! $streamer->exists || $streamer->twitch_user_id === $userId) {
            return;
        }

        $streamer->forceFill(['twitch_user_id' => $userId]);
        $streamer->saveQuietly();
    }

    private function validYoutubeChannelId(?string $id): bool
    {
        return is_string($id)
            && (bool) preg_match('/^UC[A-Za-z0-9_-]{20,}$/', $id);
    }
}
