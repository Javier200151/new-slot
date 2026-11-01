<?php

namespace App\Services;

use App\Models\CommunityClip;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;

class CommunityClipService
{
    /** @return array{platform:string,external_id:string,url:string} */
    public function parse(string $url): array
    {
        $parts = parse_url(trim($url));
        if (! is_array($parts) || strtolower((string) ($parts['scheme'] ?? '')) !== 'https') {
            throw ValidationException::withMessages(['url' => 'Introduce un enlace HTTPS válido de Twitch o YouTube.']);
        }

        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $platform = null;
        $slug = null;
        $youtubeShort = false;

        if ($host === 'clips.twitch.tv' && preg_match('/^([A-Za-z0-9_-]{5,150})$/', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'twitch';
        } elseif (in_array($host, ['twitch.tv', 'www.twitch.tv', 'm.twitch.tv'], true)
            && preg_match('~^[A-Za-z0-9_]+/clip/([A-Za-z0-9_-]{5,150})$~', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'twitch';
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com', 'music.youtube.com'], true)) {
            if ($path === 'watch') {
                parse_str((string) ($parts['query'] ?? ''), $query);
                $videoId = $query['v'] ?? null;
                $slug = is_string($videoId) ? $videoId : null;
            } elseif (preg_match('~^(shorts|live|embed)/([A-Za-z0-9_-]{11})$~', $path, $matches)) {
                $youtubeShort = $matches[1] === 'shorts';
                $slug = $matches[2];
            }

            if (is_string($slug) && preg_match('/^[A-Za-z0-9_-]{11}$/', $slug)) {
                $platform = 'youtube';
            }
        } elseif (in_array($host, ['youtu.be', 'www.youtu.be'], true)
            && preg_match('/^([A-Za-z0-9_-]{11})$/', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'youtube';
        }

        if (! $platform) {
            throw ValidationException::withMessages([
                'url' => 'Introduce un clip de Twitch o un enlace de YouTube (vídeo normal o Short).',
            ]);
        }

        return [
            'platform' => $platform,
            'external_id' => $slug,
            'url' => $platform === 'twitch'
                ? 'https://clips.twitch.tv/' . $slug
                : ($youtubeShort
                    ? 'https://www.youtube.com/shorts/' . $slug
                    : 'https://www.youtube.com/watch?v=' . $slug),
        ];
    }

    /** Crear solo si no existía: una retirada nunca vuelve a publicar el mismo clip. */
    public function addManual(User $user, string $url, ?string $title, ?int $eventId = null, array $protagonistIds = []): CommunityClip
    {
        $data = $this->parse($url);
        if (CommunityClip::withTrashed()->where('platform', $data['platform'])->where('external_id', $data['external_id'])->exists()) {
            throw ValidationException::withMessages(['url' => 'Este clip ya se ha compartido en Squad ALPHA.']);
        }

        $protagonistIds = array_values(array_unique(array_map('intval', $protagonistIds)));
        $meta = $data['platform'] === 'twitch'
            ? $this->twitchClip($data['external_id'])
            : $this->youtubeVideo($data['external_id']);
        $clip = new CommunityClip(array_merge($data, [
            'user_id' => $user->id,
            'event_id' => $eventId,
            'protagonist_user_id' => $protagonistIds[0] ?? null,
            'source' => 'manual',
            'title' => trim((string) $title) ?: (string) ($meta['title'] ?? 'Clip de la comunidad'),
            'thumbnail_url' => $meta['thumbnail_url'] ?? ($data['platform'] === 'youtube' ? 'https://i.ytimg.com/vi/' . $data['external_id'] . '/hqdefault.jpg' : null),
            'broadcaster_name' => $meta['broadcaster_name'] ?? null,
            'game_id' => $meta['game_id'] ?? null,
            'duration_seconds' => isset($meta['duration']) ? (int) ceil((float) $meta['duration']) : null,
            'clip_created_at' => $meta['created_at'] ?? now(),
            'is_visible' => true,
        ]));
        $clip->save();
        $clip->protagonists()->sync($protagonistIds);
        if ($protagonistIds !== []) {
            app(\App\Services\AuditLogger::class)->change(
                $clip,
                'protagonistas_asignados',
                [],
                ['protagonist_user_ids' => $protagonistIds],
                ['relation' => 'community_clip_protagonists'],
            );
        }
        return $clip;

    }

    public function parseProtagonistIds(?string $raw): array
    {
        $ids = collect(explode(',', (string) $raw))->map(fn ($id) => (int) trim($id))
            ->filter(fn ($id) => $id > 0)->unique()->values()->all();
        if (count($ids) > 12) {
            throw ValidationException::withMessages(['protagonist_user_ids' => 'Selecciona un máximo de 12 protagonistas.']);
        }
        if ($ids && User::query()->whereIn('id', $ids)->count() !== count($ids)) {
            throw ValidationException::withMessages(['protagonist_user_ids' => 'Hay protagonistas que no existen.']);
        }
        return $ids;
    }

    public function supplementContext(CommunityClip $clip, ?int $eventId, array $protagonistIds): void
    {
        if ($eventId && ! $clip->event_id) {
            $clip->event_id = $eventId;
        }
        if ($protagonistIds !== []) {
            $existing = $clip->protagonists()->pluck('users.id')->map(fn ($id) => (int) $id)->all();
            $newIds = array_values(array_unique(array_merge($existing, array_map('intval', $protagonistIds))));
            $clip->protagonists()->sync($newIds);
            if ($newIds !== $existing) {
                app(\App\Services\AuditLogger::class)->change(
                    $clip,
                    'protagonistas_modificados',
                    ['protagonist_user_ids' => $existing],
                    ['protagonist_user_ids' => $newIds],
                    ['relation' => 'community_clip_protagonists'],
                );
            }
            if (! $clip->protagonist_user_id) {
                $clip->protagonist_user_id = $newIds[0] ?? null;
            }
        }
        if ($clip->isDirty()) {
            $clip->save();
        }
    }

    /**
     * Consulta opcional de la fecha y título originales. No bloquea una
     * publicación manual si el servicio externo o la clave no están disponibles.
     */
    public function youtubeVideo(string $id): ?array
    {
        $key = trim((string) config('services.youtube.api_key', ''));
        if ($key === '') {
            return null;
        }

        try {
            $response = Http::timeout(7)->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet',
                'id' => $id,
                'key' => $key,
            ]);
            if (! $response->successful()) {
                return null;
            }

            $snippet = $response->json('items.0.snippet');
            if (! is_array($snippet)) {
                return null;
            }

            return [
                'title' => $snippet['title'] ?? null,
                'thumbnail_url' => data_get($snippet, 'thumbnails.high.url')
                    ?: data_get($snippet, 'thumbnails.medium.url'),
                'broadcaster_name' => $snippet['channelTitle'] ?? null,
                'created_at' => $snippet['publishedAt'] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    public function twitchClient(): ?array
    {
        $id = trim((string) config('services.twitch.client_id'));
        $secret = trim((string) config('services.twitch.client_secret'));
        if ($id === '' || $secret === '') {
            return null;
        }
        $token = \Illuminate\Support\Facades\Cache::remember('twitch.app-token', now()->addHours(12), function () use ($id, $secret) {
            $response = Http::asForm()->timeout(8)->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $id, 'client_secret' => $secret, 'grant_type' => 'client_credentials',
            ]);
            return $response->successful() ? $response->json('access_token') : null;
        });
        return $token ? ['Client-Id' => $id, 'Authorization' => 'Bearer ' . $token] : null;
    }

    public function twitchClip(string $id): ?array
    {
        $headers = $this->twitchClient();
        if (! $headers) return null;
        $response = Http::timeout(8)->withHeaders($headers)->get('https://api.twitch.tv/helix/clips', ['id' => $id]);
        return $response->successful() ? $response->json('data.0') : null;
    }
}
