<?php

namespace App\Services;

use Carbon\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class HomepageInstagramService
{
    public function latest(int $limit = 3): Collection
    {
        $limit = max(1, min(12, $limit));
        $token = trim((string) config('services.instagram.access_token'));

        if ($token === '') {
            return collect();
        }

        // Igual que con los VODs: la caché guarda solo tipos escalares/arrays
        // para que sea compatible con serializable_classes=false de Laravel 12.
        $cached = Cache::remember(
            'homepage.instagram.v1.' . $limit,
            now()->addMinutes(15),
            fn (): array => $this->fetchMedia($token, $limit)
        );

        return collect(is_array($cached) ? $cached : [])
            ->map(function (array $post): array {
                $timestamp = $post['timestamp'] ?? null;

                if (is_string($timestamp) && $timestamp !== '') {
                    try {
                        $post['timestamp'] = Carbon::parse($timestamp);
                    } catch (Throwable) {
                        $post['timestamp'] = null;
                    }
                }

                return $post;
            });
    }

    private function fetchMedia(string $token, int $limit): array
    {
        try {
            $response = Http::timeout(7)
                ->retry(1, 200)
                ->acceptJson()
                ->get('https://graph.instagram.com/me/media', [
                    'fields' => 'id,caption,media_type,media_url,permalink,thumbnail_url,timestamp,username',
                    'limit' => $limit,
                    'access_token' => $token,
                ]);

            if (! $response->successful()) {
                return [];
            }

            $expectedUsername = strtolower(trim((string) config('services.instagram.username', 'squadalpha_es')));

            return collect($response->json('data', []))
                ->filter(fn ($post): bool => is_array($post) && filled($post['permalink'] ?? null))
                ->filter(function (array $post) use ($expectedUsername): bool {
                    if ($expectedUsername === '') {
                        return true;
                    }

                    $apiUsername = strtolower(trim((string) ($post['username'] ?? '')));

                    // Algunos tokens/endpoints no devuelven username aunque se
                    // solicite. En ese caso no descartamos el contenido.
                    return $apiUsername === '' || $apiUsername === $expectedUsername;
                })
                ->take($limit)
                ->map(function (array $post): array {
                    $mediaType = strtoupper((string) ($post['media_type'] ?? 'IMAGE'));
                    $image = $mediaType === 'VIDEO'
                        ? ($post['thumbnail_url'] ?? $post['media_url'] ?? null)
                        : ($post['media_url'] ?? $post['thumbnail_url'] ?? null);

                    return [
                        'id' => (string) ($post['id'] ?? ''),
                        'caption' => trim((string) ($post['caption'] ?? '')),
                        'media_type' => $mediaType,
                        'image' => $image,
                        'permalink' => (string) ($post['permalink'] ?? ''),
                        'timestamp' => ! empty($post['timestamp'])
                            ? Carbon::parse($post['timestamp'])->toIso8601String()
                            : null,
                        'username' => (string) ($post['username'] ?? config('services.instagram.username', 'squadalpha_es')),
                    ];
                })
                ->values()
                ->all();
        } catch (Throwable) {
            return [];
        }
    }
}
