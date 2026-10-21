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
        $allowedCategoryFingerprint = app(TwitchArmaCategoryService::class)->fingerprint();
        $cacheKey = 'homepage.latest-vods.library-v6.' . $allowedCategoryFingerprint . '.' . max(1, $limit);

        $cached = Cache::remember($cacheKey, now()->addMinutes(15), function () use ($limit): array {
            $items = collect();

            Streamer::query()
                ->with('user')
                ->where('enable', true)
                ->orderBy('id')
                ->get()
                ->each(function (Streamer $streamer) use ($items): void {
                    // Los vídeos de YouTube no se filtran por categoría.
                    foreach ($this->youtubeVods($streamer) as $vod) {
                        $items->push($vod);
                    }

                    // Sólo los VODs de Twitch requieren categoría permitida.
                    foreach ($this->twitchVods($streamer) as $vod) {
                        $items->push($vod);
                    }
                });

            // Se conserva el respaldo existente de vídeos de YouTube publicados
            // manualmente. No se utilizan como respaldo los enlaces a canales Twitch.
            foreach ($this->fallbackYoutubeStreams() as $vod) {
                $items->push($vod);
            }

            // Ocultar en Emisiones también funciona si YouTube aparece por RSS.
            $hiddenKeys = Stream::query()
                ->where('visible_on_home', false)
                ->pluck('stream_url')
                ->map(fn (string $url): string => $this->normaliseUrl($url))
                ->all();

            return $items
                ->reject(fn (array $vod): bool => in_array(
                    $this->normaliseUrl((string) ($vod['url'] ?? '')), $hiddenKeys, true
                ))
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

            foreach (array_slice($entries[1] ?? [], 0, 15) as $entry) {
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
        // Sin conexión con Twitch se conservan los VODs ya registrados.
        $persisted = fn (): array => Stream::query()
            ->where('streamer_id', $streamer->id)
            ->where('platform', 'twitch')
            ->where(function ($query): void {
                $query->where('is_vod', true)
                    ->orWhere('stream_url', 'like', '%twitch.tv/videos/%');
            })
            ->where('visible_on_home', true)
            ->orderByDesc('published_at')
            ->get()
            ->map(function (Stream $record) use ($streamer): array {
                $thumb = (string) ($record->thumbnail_url ?? '');
                return [
                    'platform' => 'twitch',
                    'url' => $record->stream_url,
                    'title' => $record->title ?: 'VOD de Squad ALPHA',
                    'thumbnail' => str_replace(['%{width}', '%{height}'], ['640', '360'], $thumb) ?: null,
                    'published_at' => $record->published_at ?? $record->started_at ?? $record->ended_at ?? $record->created_at,
                    'streamer' => $streamer->user?->nick ?? 'Squad ALPHA',
                ];
            })->all();

        $clientId = config('services.twitch.client_id');
        $clientSecret = config('services.twitch.client_secret');
        $login = $this->extractTwitchLogin($streamer->twitch_channel);
        if (! $clientId || ! $clientSecret || (! $streamer->twitch_user_id && ! $login)) {
            return $persisted();
        }

        try {
            $token = $this->twitchToken($clientId, $clientSecret);
            if (! $token) {
                return $persisted();
            }
            $userId = $streamer->twitch_user_id
                ?: $this->resolveTwitchUserId($streamer, $login, $clientId, $token);
            if (! $userId) {
                return $persisted();
            }
            $response = Http::timeout(5)
                ->withHeaders([
                    'Client-Id' => $clientId,
                    'Authorization' => 'Bearer ' . $token,
                ])
                ->get('https://api.twitch.tv/helix/videos', [
                    'user_id' => $userId,
                    'first' => 100,
                    'type' => 'archive',
                ]);
            if ($response->successful()) {
                $filter = app(TwitchArmaCategoryService::class);
                $library = app(TwitchVodLibraryService::class);
                $approved = $filter->approvedRecentVodIds();
                foreach ($response->json('data', []) as $video) {
                    if (! is_array($video) || empty($video['id'])) {
                        continue;
                    }
                    // Cualquiera de las tres condiciones O un directo que ya
                    // estuviera autorizado permite guardar esta grabación.
                    if ($filter->allowsVod($video, $approved)
                        || $library->wasApprovedLive($streamer, $video)
                        || $library->wasManuallyPublished($streamer, $video)) {
                        $library->saveVod($streamer, $video);
                    }
                }
            }
        } catch (Throwable $e) {
            // Un fallo externo de Twitch no hace desaparecer los VODs guardados.
        }

        return $persisted();
    }

    public function invalidateCache(): void
    {
        $fingerprint = app(TwitchArmaCategoryService::class)->fingerprint();
        for ($limit = 1; $limit <= 24; $limit++) {
            Cache::forget('homepage.latest-vods.library-v6.' . $fingerprint . '.' . $limit);
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
                            'User-Agent' => 'Mozilla/5.0 (compatible; SquadAlpha/1.0)',
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
            ->where('visible_on_home', true)
            ->whereNotNull('ended_at')
            ->whereHas('streamer', fn ($query) => $query->where('enable', true))
            ->latest('ended_at')
            ->limit(40)
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
        $url = rtrim(strtolower(trim($url)), '/');
        if (preg_match('~(?:youtube\.com/(?:watch\?v=|live/|shorts/)|youtu\.be/)([a-z0-9_-]+)~i', $url, $match)) {
            return 'youtube:' . $match[1];
        }
        if (preg_match('~twitch\.tv/videos/(\d+)~i', $url, $match)) {
            return 'twitch:' . $match[1];
        }
        return $url;
    }
}
