<?php

namespace App\Support;

use Carbon\CarbonInterface;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/** Conmemoración anual del 27 de abril. Los overrides son persistentes en MySQL. */
final class FallujahEasterEgg
{
    public const FEATURE_KEY = 'fallujah_2012';
    public const ZONE = 'Europe/Madrid';

    /** 'auto' sigue el calendario, 'on' fuerza la aparición y 'off' la bloquea. */
    public static function mode(): string
    {
        try {
            $mode = DB::table('sqa_easter_egg_overrides')
                ->where('key', self::FEATURE_KEY)
                ->value('mode');
        } catch (QueryException $exception) {
            // Durante el intervalo entre despliegue y migración no rompe la web.
            $message = strtolower($exception->getMessage());
            if (str_contains($message, 'no such table')
                || str_contains($message, "doesn't exist")
                || str_contains($message, 'base table or view not found')) {
                return 'auto';
            }

            throw $exception;
        }

        return in_array($mode, ['auto', 'on', 'off'], true) ? $mode : 'auto';
    }

    public static function visible(): bool
    {
        return SquadEasterEggs::enabled(self::FEATURE_KEY)
            && self::visibleFor(self::mode(), now(self::ZONE));
    }

    public static function visibleFor(string $mode, CarbonInterface $moment): bool
    {
        if ($mode === 'on') {
            return true;
        }

        if ($mode === 'off') {
            return false;
        }

        $local = $moment->copy()->setTimezone(self::ZONE);

        return $local->month === 4 && $local->day === 27;
    }

    public static function anniversary(): int
    {
        return max(0, now(self::ZONE)->year - 2012);
    }
}
