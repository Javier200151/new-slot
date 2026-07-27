<?php

namespace App\Support;

class PermissionCatalog
{
    public static function guard(): string
    {
        return config('newslot-permissions.guard', 'web');
    }

    public static function actions(): array
    {
        return config('newslot-permissions.actions', []);
    }

    public static function groups(): array
    {
        return config('newslot-permissions.groups', []);
    }

    public static function resources(): array
    {
        $resources = [];

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['resources'] ?? [] as $resource => $definition) {
                $resources[$resource] = [
                    'label' => $definition['label'] ?? $resource,
                    'group' => $groupKey,
                    'actions' => $definition['actions']
                        ?? array_keys(self::actions()),
                ];
            }
        }

        return $resources;
    }

    public static function actionsFor(string $resource): array
    {
        return self::resources()[$resource]['actions'] ?? [];
    }

    public static function actionOptionsFor(string $resource): array
    {
        $allowedActions = array_flip(
            self::actionsFor($resource)
        );

        return array_intersect_key(
            self::actions(),
            $allowedActions
        );
    }

    public static function permissionNames(
        bool $includeFilamentAccess = true
    ): array {
        $permissions = $includeFilamentAccess
            ? ['filament.access']
            : [];

        foreach (self::resources() as $resource => $definition) {
            foreach ($definition['actions'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return array_values(array_unique($permissions));
    }

    public static function fieldName(string $resource): string
    {
        /*
         * El permiso conserva el guion:
         * user-metopas.view
         *
         * Pero el campo Filament usa guion bajo:
         * permissions_user_metopas
         */
        return 'permissions_'.str_replace(
            '-',
            '_',
            $resource
        );
    }
}