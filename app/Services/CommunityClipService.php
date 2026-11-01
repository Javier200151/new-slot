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
            throw ValidationException::withMessages(['url' => 'Introduce un enlace HTTPS de Twitch Clips o YouTube Shorts.']);
        }
        $host = strtolower((string) ($parts['host'] ?? ''));
        $path = trim((string) ($parts['path'] ?? ''), '/');
        $slug = null;
        $platform = null;

        if ($host === 'clips.twitch.tv' && preg_match('/^([A-Za-z0-9_-]{5,150})$/', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'twitch';
        } elseif (in_array($host, ['twitch.tv', 'www.twitch.tv', 'm.twitch.tv'], true)
            && preg_match('~^[A-Za-z0-9_]+/clip/([A-Za-z0-9_-]{5,150})$~', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'twitch';
        } elseif (in_array($host, ['youtube.com', 'www.youtube.com', 'm.youtube.com'], true)
            && preg_match('~^shorts/([A-Za-z0-9_-]{11})$~', $path, $matches)) {
            $slug = $matches[1];
            $platform = 'youtube';
        }

        if (! $platform) {
            throw ValidationException::withMessages(['url' => 'El enlace debe ser de un clip de Twitch o un Short de YouTube.']);
        }

        return [
            'platform' => $platform,
            'external_id' => $slug,
            'url' => $platform === 'twitch'
                ? 'https://clips.twitch.tv/' . $slug
                : 'https://www.youtube.com/shorts/' . $slug,
        ];
    }

    /** Crear solo si no existía: una retirada nunca vuelve a publicar el mismo clip. */
    public function addManual(User $user, string $url, ?string $title, ?int $eventId = null, ?int $protagonistId = null): CommunityClip
    {
        $data = $this->parse($url);
        if (CommunityClip::withTrashed()->where('platform', $data['platform'])->where('external_id', $data['external_id'])->exists()) {
            throw ValidationException::withMessages(['url' => 'Este clip ya se ha compartido en Squad ALPHA.']);
        }

        $meta = $data['platform'] === 'twitch' ? $this->twitchClip($data['external_id']) : null;
        $clip = new CommunityClip(array_merge($data, [
            'user_id' => $user->id,
            'event_id' => $eventId,
            'protagonist_user_id' => $protagonistId,
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
        return $clip;
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
