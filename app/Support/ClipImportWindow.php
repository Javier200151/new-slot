<?php

namespace App\Support;

use App\Models\Streamer;
use Carbon\CarbonImmutable;

class ClipImportWindow
{
    public static function start(Streamer $streamer): CarbonImmutable
    {
        $now = CarbonImmutable::now('UTC');

        return match ($streamer->clip_import_window) {
            '1m' => $now->subMonth(),
            '6m' => $now->subMonths(6),
            '1y' => $now->subYear(),
            default => $now->subDays(7),
        };
    }
}
