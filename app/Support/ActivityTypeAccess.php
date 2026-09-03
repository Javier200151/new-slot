<?php

namespace App\Support;

use App\Models\ActivityType;
use App\Models\User;

class ActivityTypeAccess
{
    public static function can(
        ?User $user,
        string $resource,
        string $action,
        int|string|null $activityTypeId,
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (blank($activityTypeId)) {
            return false;
        }

        return $user->can(
            PermissionCatalog::activityTypePermissionName(
                $resource,
                (int) $activityTypeId,
                $action,
            )
        );
    }

    public static function canAny(
        ?User $user,
        string $resource,
        string $action,
    ): bool {
        return self::allowedTypeIds($user, $resource, $action) !== [];
    }

    public static function allowedTypeIds(
        ?User $user,
        string $resource,
        string $action,
    ): array {
        if (! $user) {
            return [];
        }

        $typeIds = ActivityType::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($user->hasRole('admin')) {
            return $typeIds;
        }

        return array_values(array_filter(
            $typeIds,
            fn (int $activityTypeId): bool => self::can(
                $user,
                $resource,
                $action,
                $activityTypeId,
            )
        ));
    }
}
