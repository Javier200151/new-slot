<?php

namespace App\Support;

use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

/** Lectura defensiva: un despliegue anterior a migrate no debe romper la web pública. */
final class SquadEasterEggs
{
    public const KEYS = ['grenade', 'error404', 'nvg', 'zeus', 'konami', 'fallujah_2012'];

    /** @var array<string, array<string, mixed>|null> */
    private static array $cache = [];

    public static function resetCache(): void
    {
        self::$cache = [];
    }

    public static function settings(string $key): ?array
    {
        if (! in_array($key, self::KEYS, true)) {
            return null;
        }

        if (array_key_exists($key, self::$cache)) {
            return self::$cache[$key];
        }

        try {
            $row = DB::table('sqa_easter_eggs')->where('key', $key)->first();
        } catch (QueryException $e) {
            if (self::missingTable($e)) {
                return self::$cache[$key] = null;
            }
            throw $e;
        }

        return self::$cache[$key] = $row ? (array) $row : null;
    }

    public static function enabled(string $key): bool
    {
        return (bool) (self::settings($key)['enabled'] ?? false);
    }

    public static function publicMediaUrl(string $key, string $column): ?string
    {
        if (! in_array($column, ['image_path', 'audio_path'], true)) {
            return null;
        }

        $path = self::settings($key)[$column] ?? null;
        if (! is_string($path) || $path === '') {
            return null;
        }

        // En local (localhost:8000) o bajo proxy, APP_URL puede apuntar a
        // un host/puerto diferente. Usar ruta del mismo origen que la web.
        $url = Storage::disk('public')->url($path);
        $urlPath = parse_url($url, PHP_URL_PATH);

        return is_string($urlPath) && str_starts_with($urlPath, '/') ? $urlPath : null;
    }

    /** Ruta EXACTA: nunca activar granadas en fichas, posts, formularios u otros paths. */
    public static function grenadeAllowedOn(string $path): bool
    {
        return in_array('/'.trim($path, '/'), [
            '/', '/eventos', '/area/foro', '/actividades', '/usuarios',
            '/comunidad/organigrama', '/metopas', '/tesoreria',
        ], true);
    }

    public static function random404Phrase(): ?string
    {
        try {
            $phrases = DB::table('sqa_easter_egg_404_phrases')
                ->where('enabled', true)
                ->pluck('phrase')->filter(fn ($phrase) => trim((string) $phrase) !== '');
        } catch (QueryException $e) {
            if (self::missingTable($e)) {
                return null;
            }
            throw $e;
        }

        return $phrases->isEmpty() ? null : (string) $phrases->random();
    }

    private static function missingTable(QueryException $e): bool
    {
        $message = strtolower($e->getMessage());
        return str_contains($message, "doesn't exist")
            || str_contains($message, 'no such table')
            || str_contains($message, 'base table or view not found');
    }
}
