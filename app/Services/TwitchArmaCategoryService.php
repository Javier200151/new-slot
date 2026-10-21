<?php

namespace App\Services;

use App\Models\TwitchCategorySetting;
use App\Models\Event;
use Carbon\Carbon;
use Carbon\CarbonInterface;
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
        return sha1(json_encode([
            $this->allowedNames(), $this->titleKeywords(),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    }

    /** @return list<string> */
    public function titleKeywords(): array
    {
        return TwitchCategorySetting::current()->title_keywords ?? [];
    }

    /** @return list<string> */
    public function excludedVodIds(): array
    {
        return TwitchCategorySetting::current()->excluded_vod_ids ?? [];
    }

    /** Una sola coincidencia autoriza el directo automático. */
    public function allowsLive(array $stream): bool
    {
        if ($this->categoryMatches($stream) || $this->titleMatches((string) ($stream['title'] ?? ''))) {
            return true;
        }

        try {
            // El directo puede comenzar antes del evento y continuar durante él.
            $started = filled($stream['started_at'] ?? null)
                ? Carbon::parse($stream['started_at'])
                : now();
            return $this->overlapsCalendarEvent($started, now());
        } catch (\Throwable) {
            return false;
        }
    }

    /** VOD: categoría verificada, título o cruce de horario; basta una condición. */
    public function allowsVod(array $video, array $approvedCategoryVodIds): bool
    {
        if (in_array((string) ($video['id'] ?? ''), $approvedCategoryVodIds, true)) {
            return true;
        }
        if ($this->titleMatches((string) ($video['title'] ?? ''))) {
            return true;
        }
        try {
            if (empty($video['created_at']) && empty($video['published_at'])) {
                return false;
            }
            $start = Carbon::parse($video['created_at'] ?? $video['published_at']);
            $durationSeconds = $this->vodDurationSeconds((string) ($video['duration'] ?? ''));
            // Si Twitch no proporciona duración, sólo se comprueba su inicio.
            return $this->overlapsCalendarEvent($start, $start->copy()->addSeconds($durationSeconds));
        } catch (\Throwable) {
            return false;
        }
    }

    public function titleMatches(string $title): bool
    {
        $title = mb_strtolower($title, 'UTF-8');
        foreach ($this->titleKeywords() as $word) {
            $word = mb_strtolower(trim((string) $word), 'UTF-8');
            if ($word !== '' && mb_strpos($title, $word, 0, 'UTF-8') !== false) {
                return true;
            }
        }
        return false;
    }

    private function categoryMatches(array $stream): bool
    {
        $name = mb_strtolower(trim((string) ($stream['game_name'] ?? '')), 'UTF-8');
        if ($name === '') {
            return false;
        }
        foreach ($this->allowedNames() as $allowed) {
            if (mb_strtolower(trim((string) $allowed), 'UTF-8') === $name) {
                // Twitch facilita game_name y game_id en el propio directo.
                $id = trim((string) ($stream['game_id'] ?? ''));
                $ids = $this->allowedGameIds();
                return $id === '' || $ids === [] || in_array($id, $ids, true);
            }
        }
        return false;
    }

    private function overlapsCalendarEvent(CarbonInterface $started, CarbonInterface $ended): bool
    {
        if ($ended->lessThan($started)) {
            return false;
        }

        return $this->matchingCalendarEvent($started, $ended) !== null;
    }

    /**
     * Devuelve un evento con una ventana que se solapa con la emisión.
     * La ventana es [inicio - 30 minutos, fin + 30 minutos].
     * No exige que el directo se haya iniciado en ella: basta con que
     * cualquier parte del directo coincida con el intervalo.
     */
    public function matchingCalendarEvent(CarbonInterface $started, CarbonInterface $ended): ?Event
    {
        if ($ended->lessThan($started)) {
            return null;
        }

        // Compartimos las ventanas del calendario durante 60 segundos.
        // Solo se cachean arrays, nunca modelos Eloquent.
        $windows = Cache::remember('sqa.twitch.calendar-windows.v1', now()->addMinute(), function (): array {
            return Event::query()
                ->whereHas('eventStatus', fn ($query) => $query->where('name', '!=', 'CANCELADO'))
                ->get(['id', 'date', 'end_date', 'duration', 'event_status_id'])
                ->map(fn (Event $event): array => [
                    'id' => $event->id,
                    'start' => $event->date?->toIso8601String(),
                    'end' => $event->end_date?->toIso8601String(),
                    'duration' => (int) ($event->duration ?? 0),
                ])->all();
        });

        foreach ($windows as $window) {
            if (empty($window['start'])) {
                continue;
            }
            $begin = Carbon::parse($window['start']);
            if ($begin->gt($ended->copy()->addMinutes(30))) {
                continue;
            }
            $end = ! empty($window['end'])
                ? Carbon::parse($window['end'])
                : $begin->copy()->addMinutes(max(0, (int) ($window['duration'] ?? 0)));

            if ($begin->copy()->subMinutes(30)->lessThanOrEqualTo($ended)
                && $end->copy()->addMinutes(30)->greaterThanOrEqualTo($started)) {
                return Event::query()->find($window['id']);
            }
        }

        return null;
    }

    private function vodDurationSeconds(string $duration): int
    {
        if (! preg_match('/^(?:(\d+)h)?(?:(\d+)m)?(?:(\d+)s)?$/i', $duration, $parts)) {
            return 0;
        }
        return (int) ($parts[1] ?? 0) * 3600 + (int) ($parts[2] ?? 0) * 60 + (int) ($parts[3] ?? 0);
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
