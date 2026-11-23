<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Addon;

/** One authoritative catalog resolution for public API, launch requests and the panel. */
final class ActivityAddons
{
    /** @return list<int> */
    public static function ids(Activity $activity): array
    {
        if ($activity->platform?->isReforger() || ! ($activity->activityType?->usesAddons() ?? true)) {
            return [];
        }

        return array_values(array_unique(array_filter(
            array_map('intval', (array) ($activity->addons['addon_ids'] ?? [])),
            static fn (int $id): bool => $id > 0,
        )));
    }

    /** @return list<string> Names in the exact catalog spelling, alphabetically sorted. */
    public static function names(Activity $activity): array
    {
        $ids = self::ids($activity);
        if ($ids === []) { return []; }
        return Addon::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();
    }

    /** @return list<int> IDs that refer to deleted catalog entries. */
    public static function missingIds(Activity $activity): array
    {
        $ids = self::ids($activity);
        if ($ids === []) { return []; }
        $existing = Addon::query()->whereIn('id', $ids)->pluck('id')->map('intval')->all();
        return array_values(array_diff($ids, $existing));
    }
}
