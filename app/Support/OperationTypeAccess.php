<?php

namespace App\Support;

use App\Models\OperationType;
use App\Models\User;

class OperationTypeAccess
{
    public static function can(
        ?User $user,
        string $resource,
        string $action,
        int|string|null $operationTypeId,
    ): bool {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        if (blank($operationTypeId)) {
            return false;
        }

        return $user->can(
            PermissionCatalog::operationTypePermissionName(
                $resource,
                (int) $operationTypeId,
                $action,
            )
        );
    }

    public static function canAny(
        ?User $user,
        string $resource,
        string $action,
    ): bool {
        return self::allowedTypeIds(
            $user,
            $resource,
            $action,
        ) !== [];
    }

    public static function allowedTypeIds(
        ?User $user,
        string $resource,
        string $action,
    ): array {
        if (! $user) {
            return [];
        }

        $typeIds = OperationType::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        if ($user->hasRole('admin')) {
            return $typeIds;
        }

        return array_values(
            array_filter(
                $typeIds,
                fn (int $operationTypeId): bool => self::can(
                    $user,
                    $resource,
                    $action,
                    $operationTypeId,
                )
            )
        );
    }
}
