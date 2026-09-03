<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\ActivityType;

class ActivityTypeConfiguration
{
    private static array $typeCache = [];

    public static function find(mixed $activityTypeId): ?ActivityType
    {
        if (blank($activityTypeId)) {
            return null;
        }

        $id = (int) $activityTypeId;

        if (! array_key_exists($id, self::$typeCache)) {
            self::$typeCache[$id] = ActivityType::query()->find($id);
        }

        return self::$typeCache[$id];
    }

    public static function normalizeActivityData(array $data): array
    {
        $type = self::find($data['operation_type_id'] ?? null);

        if (! $type) {
            return $data;
        }

        if (! $type->supportsOcap()) {
            $data['ocap'] = false;
        }

        if (! $type->supportsRespawn()) {
            $data['respawn'] = false;
        }

        if (! $type->supportsJip()) {
            $data['jip'] = false;
        }

        if (! $type->awardsMetopa()) {
            $data['metopa_id'] = null;
        }

        return $data;
    }

    /** Alias temporal para consumidores que aún usan el nombre anterior. */
    public static function normalizeOperationData(array $data): array
    {
        return self::normalizeActivityData($data);
    }

    public static function normalizeEventData(array $data, ?int $activityId = null): array
    {
        $activityId ??= isset($data['operation_id'])
            ? (int) $data['operation_id']
            : null;

        if (! $activityId) {
            return $data;
        }

        $activity = Activity::query()
            ->with('activityType')
            ->find($activityId);

        if (! $activity) {
            return $data;
        }

        if (! ($activity->activityType?->usesEventResult() ?? true)) {
            $data['event_result_id'] = null;
        }

        if (
            ! ($activity->activityType?->supportsOcap() ?? true)
            || ! $activity->ocap
        ) {
            $data['ocap_url'] = null;
        }

        return $data;
    }
}
