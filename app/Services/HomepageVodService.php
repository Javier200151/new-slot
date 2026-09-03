<?php

namespace App\Services;

use App\Models\Stream;
use App\Models\Streamer;
use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HomepageVodService
{
    public function latest(int $limit = 6): Collection
    {
        // Laravel 12 no permite deserializar clases PHP desde la caché por
        // defecto (config/cache.php => serializable_classes = false). Por eso
        // aquí guardamos únicamente arrays y strings, nunca Collection/Carbon.
        $cacheKey = 'homepage.latest-vods.v2.' . max(1, $limit);

        $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($limit): array {
            $items = collect();

            Streamer::query()
                ->with('user')
                ->where('enable', true)
                ->orderBy('id')
                ->get()
                ->each(function (Streamer $streamer) use ($items): void {
                    foreach ($this->youtubeVods($streamer) as $vod) {
                        $items->push($vod);
                    }

                    foreach ($this->twitchVods($streamer) as $vod) {
                        $items->push($vod);
                    }
                });

            // También conservamos como respaldo los vídeos de YouTube que los
            // streamers hayan enlazado directamente desde NewSlot. En Twitch
            // no usamos el histórico local como VOD porque normalmente guarda
            // la URL del canal, no la URL concreta del vídeo archivado.
            foreach ($this->fallbackYoutubeStreams() as $vod) {
                $items->push($vod);
            }

            return $items
                ->filter(fn (array $vod): bool => filled($vod['url'] ?? null))
                ->unique(fn (array $vod): string => $this->normaliseUrl((string) $vod['url']))
                ->sortByDesc(function (array $vod): int {
                    $publishedAt = $vod['published_at'] ?? null;

                    if ($publishedAt instanceof Carbon) {
                        return $publishedAt->getTimestamp();
                    }

                    if (is_string($publishedAt) && $publishedAt !== '') {
                        try {
                            return Carbon::parse($publishedAt)->getTimestamp();
                        } catch (Throwable) {
                            return 0;
                        }
                    }

                    return 0;
                })
                ->take($limit)
                ->values()
                ->map(function (array $vod): array {
                    $publishedAt = $vod['published_at'] ?? null;

                    if ($publishedAt instanceof Carbon) {
                        $vod['published_at'] = $publishedAt->toIso8601String();
                    } elseif ($publishedAt !== null) {
                        $vod['published_at'] = (string) $publishedAt;
                    }

                    return $vod;
                })
                ->all();
        });

        // La vista sigue recibiendo Carbon para poder usar ->format(), pero
        // esa conversión se hace después de leer la caché.
        return collect(is_array($cached) ? $cached : [])
            ->map(function (array $vod): array {
                $publishedAt = $vod['published_at'] ?? null;

                if (is_string($publishedAt) && $publishedAt !== '') {
                    try {
                        $vod['published_at'] = Carbon::parse($publishedAt);
                    } catch (Throwable) {
                        $vod['published_at'] = null;
                    }
                }

                return $vod;
            });
    }

    /**
     * YouTube no necesita credenciales. A partir del enlace guardado en
     * Streamers resolvemos el Channel ID (si no está ya almacenado) y usamos
     * el feed RSS oficial del canal.
     */
    private function youtubeVods(Streamer $streamer): array
    {
        $channelId = $this->resolveYoutubeChannelId($streamer);

        if (! $channelId) {
            return [];
        }

        try {
            $response = Http::timeout(5)
                ->retry(1, 150)
                ->get('https://www.youtube.com/feeds/videos.xml', [
                    'channel_id' => $channelId,
                ]);

            if (! $response->successful()) {
                return [];
            }

            preg_match_all('~<entry>(.*?)</entry>~s', $response->body(), $entries);
            $vods = [];

            foreach (array_slice($entries[1] ?? [], 0, 3) as $entry) {
                if (! preg_match('~<yt:videoId>([^<]+)</yt:videoId>~', $entry, $videoMatch)) {
                    continue;
                }

                preg_match('~<title>(.*?)</title>~s', $entry, $titleMatch);
                preg_match('~<published>(.*?)</published>~', $entry, $publishedMatch);

                $id = trim($videoMatch[1]);
                $vods[] = [
                    'platform' => 'youtube',
                    'url' => 'https://www.youtube.com/watch?v=' . $id,
                    'title' => html_entity_decode(strip_tags($titleMatch[1] ?? 'Vídeo de Squad ALPHA'), ENT_QUOTES | ENT_XML1),
                    'thumbnail' => 'https://i.ytimg.com/vi/' . $id . '/hqdefault.jpg',
                    'published_at' => isset($publishedMatch[1]) ? Carbon::parse($publishedMatch[1]) : null,
                    'streamer' => $streamer->user?->nick ?? 'Squad ALPHA',
                ];
            }

            return $vods;
        } catch (Throwable) {
            return [];
        }
    }

    /**
     * Twitch sí exige Client-ID + OAuth para consultar oficialmente VODs.
     * No hace falta guardar manualmente el Twitch User ID: lo resolvemos a
     * partir del enlace twitch.tv/usuario que ya existe en Filament.
     */
    private function twitchVods(Streamer $streamer): array
    {
        $clientId = config('services.twitch.client_id');
        $clientSecret = config('services.twitch.client_secret');

        if (! $clientId || ! $clientSecret) {
            return [];
        }

        $login = $this->extractTwitchLogin($streamer->twitch_channel);

        if (! $streamer->twitch_user_id && ! $login) {
            return [];
        }

        try {
            $token = $this->twitchToken($clientId, $clientSecret);

            if (! $token) {
                return [];
            }

            $userId = $streamer->twitch_user_id
                ?: $this->resolveTwitchUserId($streamer, $login, $clientId, $token);

            if (! $userId) {
                return [];
            }

            $response = Http::timeout(5)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get('https://api.twitch.tv/helix/videos', [
                    'user_id' => $userId,
                    'first' => 3,
                    'type' => 'archive',
                ]);

            if (! $response->successful()) {
                return [];
            }

            return collect($response->json('data', []))->map(function (array $video) use ($streamer): array {
                $thumbnail = str_replace(['%{width}', '%{height}'], ['640', '360'], $video['thumbnail_url'] ?? '');

                return [
                    'platform' => 'twitch',
                    'url' => $video['url'] ?? ('https://www.twitch.tv/videos/' . ($video['id'] ?? '')),
                    'title' => $video['title'] ?? 'VOD de Squad ALPHA',
                    'thumbnail' => $thumbnail ?: null,
                    'published_at' => ! empty($video['published_at']) ? Carbon::parse($video['published_at']) : null,
                    'streamer' => $streamer->user?->nick ?? ($video['user_name'] ?? 'Squad ALPHA'),
                ];
            })->all();
        } catch (Throwable) {
            return [];
        }
    }

    private function resolveYoutubeChannelId(Streamer $streamer): ?string
    {
        if ($this->validYoutubeChannelId($streamer->youtube_channel_id)) {
            return $streamer->youtube_channel_id;
        }

        $url = trim((string) $streamer->youtube_channel);

        if ($url === '') {
            return null;
        }

        // youtube.com/channel/UCxxxx ya contiene directamente el ID.
        if (preg_match('~youtube\.com/channel/(UC[A-Za-z0-9_-]{20,})~i', $url, $match)) {
            return $match[1];
        }

        return Cache::remember(
            'homepage.youtube-channel-id.' . $streamer->id . '.' . sha1($url),
            now()->addDays(7),
            function () use ($url): ?string {
                try {
                    $response = Http::timeout(5)
                        ->retry(1, 150)
                        ->withHeaders([
                            'User-Agent' => 'Mozilla/5.0 (compatible; SquadAlpha-NewSlot/1.0)',
                            'Accept-Language' => 'es-ES,es;q=0.9,en;q=0.8',
                        ])
                        ->get($url);

                    if (! $response->successful()) {
                        return null;
                    }

                    $body = $response->body();
                    $patterns = [
                        '~<meta[^>]+itemprop=["\']channelId["\'][^>]+content=["\'](UC[A-Za-z0-9_-]{20,})["\']~i',
                        '~<link[^>]+rel=["\']alternate["\'][^>]+href=["\'][^"\']*feeds/videos\.xml\?channel_id=(UC[A-Za-z0-9_-]{20,})[^"\']*["\']~i',
                        '~["\']channelId["\']\s*:\s*["\'](UC[A-Za-z0-9_-]{20,})["\']~',
                        '~["\']externalId["\']\s*:\s*["\'](UC[A-Za-z0-9_-]{20,})["\']~',
                    ];

                    foreach ($patterns as $pattern) {
                        if (preg_match($pattern, $body, $match) && $this->validYoutubeChannelId($match[1])) {
                            return $match[1];
                        }
                    }
                } catch (Throwable) {
                    return null;
                }

                return null;
            }
        );
    }

    private function twitchToken(string $clientId, string $clientSecret): ?string
    {
        return Cache::remember('twitch.app-token', now()->addHours(12), function () use ($clientId, $clientSecret): ?string {
            $response = Http::asForm()->timeout(5)->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'client_credentials',
            ]);

            return $response->successful() ? $response->json('access_token') : null;
        });
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

        return Cache::remember(
            'homepage.twitch-user-id.' . $streamer->id . '.' . strtolower($login),
            now()->addDays(7),
            function () use ($login, $clientId, $token): ?string {
                $response = Http::timeout(5)
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

                return data_get($response->json(), 'data.0.id');
            }
        );
    }

    private function extractTwitchLogin(?string $url): ?string
    {
        if (! $url) {
            return null;
        }

        $parts = parse_url(trim($url));

        if (! is_array($parts)) {
            return null;
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $host = preg_replace('/^www\./', '', $host);

        if ($host !== 'twitch.tv') {
            return null;
        }

        $path = trim((string) ($parts['path'] ?? ''), '/');
        $login = explode('/', $path)[0] ?? null;

        if (! $login || ! preg_match('/^[A-Za-z0-9_]{1,25}$/', $login)) {
            return null;
        }

        return $login;
    }

    private function fallbackYoutubeStreams(): Collection
    {
        return Stream::query()
            ->with('streamer.user')
            ->where('platform', 'youtube')
            ->whereNotNull('ended_at')
            ->whereHas('streamer', fn ($query) => $query->where('enable', true))
            ->latest('ended_at')
            ->limit(12)
            ->get()
            ->map(fn (Stream $stream): array => [
                'platform' => 'youtube',
                'url' => $stream->stream_url,
                'title' => $stream->title ?: 'Retransmisión de Squad ALPHA',
                'thumbnail' => $this->youtubeThumbnail($stream->stream_url),
                'published_at' => $stream->ended_at,
                'streamer' => $stream->streamer?->user?->nick ?? 'Squad ALPHA',
            ]);
    }

    private function youtubeThumbnail(string $url): ?string
    {
        if (preg_match('~(?:youtu\.be/|youtube\.com/(?:watch\?v=|shorts/|live/))([^?&/]+)~', $url, $match)) {
            return 'https://i.ytimg.com/vi/' . $match[1] . '/hqdefault.jpg';
        }

        return null;
    }

    private function validYoutubeChannelId(?string $id): bool
    {
        return is_string($id) && (bool) preg_match('/^UC[A-Za-z0-9_-]{20,}$/', $id);
    }

    private function normaliseUrl(string $url): string
    {
        return rtrim(strtolower(trim($url)), '/');
    }
}
