<?php

namespace App\Services;

use App\Models\TwitchCategorySetting;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Catálogo oficial de Twitch y filtro de categorías públicas.
 * La restricción sólo se aplica a Twitch; YouTube se gestiona por separado.
 */
class TwitchArmaCategoryService
{
    /** @return list<string> */
    public function allowedNames(): array
    {
        return TwitchCategorySetting::current()->allowed_categories ?? [];
    }

    public function fingerprint(): string
    {
        return sha1(json_encode($this->allowedNames(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    public function allowsLive(array $stream): bool
    {
        $gameName = mb_strtolower(trim((string) ($stream['game_name'] ?? '')), 'UTF-8');
        if ($gameName === '') {
            return false;
        }

        foreach ($this->allowedNames() as $name) {
            if (mb_strtolower(trim($name), 'UTF-8') === $gameName) {
                // game_name llega de la API oficial de Twitch, no del título.
                $actualId = trim((string) ($stream['game_id'] ?? ''));
                $knownIds = $this->allowedGameIds();
                return $actualId === '' || $knownIds === [] || in_array($actualId, $knownIds, true);
            }
        }

        return false;
    }

    /**
     * Busca categorías oficiales para el selector de Filament.
     * @return array<string, string> nombre => nombre
     */
    public function searchCategories(string $search): array
    {
        $search = trim($search);
        if (mb_strlen($search) < 2) {
            return [];
        }

        $data = $this->request('https://api.twitch.tv/helix/search/categories', [
            'query' => $search,
            'first' => 30,
        ]);

        $options = [];
        foreach ($data ?? [] as $game) {
            if (! is_array($game)) {
                continue;
            }
            $name = trim((string) ($game['name'] ?? ''));
            if ($name !== '' && ! empty($game['id'])) {
                $options[$name] = $name;
            }
        }
        return $options;
    }

    /**
     * Confirma en Twitch que las categorías elegidas existen realmente.
     * Devuelve false si Twitch está temporalmente inaccesible.
     */
    public function verifyNames(array $names): bool
    {
        if ($names === []) {
            return true;
        }

        $valid = $this->gamesByNames($names);
        if ($valid === null) {
            return false;
        }

        foreach ($names as $name) {
            if (! array_key_exists(mb_strtolower(trim((string) $name), 'UTF-8'), $valid)) {
                return false;
            }
        }
        return true;
    }

    /** @return list<string> */
    public function allowedGameIds(): array
    {
        $names = $this->allowedNames();
        if ($names === []) {
            return [];
        }
        $key = 'sqa.twitch.allowed-ids.v3.' . $this->fingerprint();
        $ids = Cache::remember($key, now()->addHours(6), function () use ($names): array {
            $games = $this->gamesByNames($names);
            return $games === null ? [] : array_values($games);
        });
        return is_array($ids) ? array_values($ids) : [];
    }

    /**
     * Twitch no admite consultar los VODs de un streamer filtrando también
     * por juego. Se cruzan los VODs del canal con los de categoría oficial;
     * los VODs que no se pueden confirmar no se muestran.
     * @return list<string>
     */
    public function approvedRecentVodIds(): array
    {
        $gameIds = $this->allowedGameIds();
        if ($gameIds === []) {
            return [];
        }

        $key = 'sqa.twitch.approved-vods.v3.' . $this->fingerprint();
        $ids = Cache::remember($key, now()->addMinutes(10), function () use ($gameIds): array {
            $result = [];
            foreach ($gameIds as $gameId) {
                $after = null;
                // La consulta por categoría permite paginación; limitamos las
                // peticiones para no saturar la API ni la portada.
                for ($page = 0; $page < 3; $page++) {
                    $query = [
                        'game_id' => $gameId,
                        'type' => 'archive',
                        'sort' => 'time',
                        'first' => 100,
                    ];
                    if ($after) {
                        $query['after'] = $after;
                    }
                    $response = $this->response('https://api.twitch.tv/helix/videos', $query);
                    if ($response === null) {
                        break;
                    }
                    foreach ($response['data'] ?? [] as $video) {
                        if (is_array($video) && ! empty($video['id'])) {
                            $result[] = (string) $video['id'];
                        }
                    }
                    $after = trim((string) ($response['pagination']['cursor'] ?? ''));
                    if ($after === '' || count($response['data'] ?? []) === 0) {
                        break;
                    }
                }
            }
            return array_values(array_unique($result));
        });
        return is_array($ids) ? $ids : [];
    }

    /** @return array<string, string>|null nombre normalizado => ID */
    private function gamesByNames(array $names): ?array
    {
        if ($names === []) {
            return [];
        }

        $names = array_values(array_unique(array_filter(array_map('trim', $names))));
        if (count($names) > 100) {
            return null;
        }

        $query = implode('&', array_map(fn (string $name): string => 'name=' . rawurlencode($name), $names));
        $url = 'https://api.twitch.tv/helix/games?' . $query;
        $data = $this->request($url);
        if ($data === null) {
            return null;
        }

        $byName = [];
        foreach ($data as $game) {
            if (! is_array($game)) {
                continue;
            }
            $name = mb_strtolower(trim((string) ($game['name'] ?? '')), 'UTF-8');
            $id = trim((string) ($game['id'] ?? ''));
            if ($name !== '' && $id !== '') {
                $byName[$name] = $id;
            }
        }
        return $byName;
    }

    private function request(string $url, array $query = []): ?array
    {
        $response = $this->response($url, $query);
        return $response === null ? null : (is_array($response['data'] ?? null) ? $response['data'] : null);
    }

    private function response(string $url, array $query = []): ?array
    {
        $clientId = trim((string) config('services.twitch.client_id'));
        $token = $this->token();
        if ($clientId === '' || $token === null) {
            return null;
        }
        try {
            $http = Http::timeout(8)->withHeaders([
                'Client-Id' => $clientId,
                'Authorization' => 'Bearer ' . $token,
            ]);
            $response = $http->get($url, $query);
            return $response->successful() && is_array($response->json())
                ? $response->json()
                : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function token(): ?string
    {
        $clientId = trim((string) config('services.twitch.client_id'));
        $secret = trim((string) config('services.twitch.client_secret'));
        if ($clientId === '' || $secret === '') {
            return null;
        }
        try {
            $key = 'sqa.twitch.categories-token.' . sha1($clientId);
            $cached = Cache::get($key);
            if (is_string($cached) && $cached !== '') {
                return $cached;
            }
            $response = Http::asForm()->timeout(7)->post('https://id.twitch.tv/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $secret,
                'grant_type' => 'client_credentials',
            ]);
            if (! $response->successful()) {
                return null;
            }
            $token = trim((string) $response->json('access_token'));
            if ($token === '') {
                return null;
            }
            Cache::put($key, $token, now()->addSeconds(max(300, (int) $response->json('expires_in', 3600) - 300)));
            return $token;
        } catch (Throwable) {
            return null;
        }
    }
}
