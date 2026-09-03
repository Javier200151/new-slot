<?php

namespace App\Support;

use App\Models\ActivityType;
use Illuminate\Support\Facades\Schema;

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
        $groups = config('newslot-permissions.groups', []);

        if (! isset($groups['community'])) {
            return $groups;
        }

        /*
         * Las categorías del foro ya no son un catálogo fijo. Cada categoría
         * registrada en Filament añade automáticamente su bloque de permisos a
         * Roles, manteniendo los nombres históricos de las categorías internas.
         */
        foreach (CommunityForumCategory::permissionResources() as $resource => $label) {
            $groups['community']['resources'][$resource] = [
                'label' => 'Foro · ' . $label,
                'actions' => [
                    'create' => 'Publicar nuevos hilos',
                    'reply' => 'Responder a hilos',
                    'poll' => 'Crear y gestionar votaciones',
                    'moderate' => 'Cerrar, reabrir y fijar hilos',
                    'delete' => 'Eliminar hilos y respuestas',
                ],
            ];
        }

        return $groups;
    }

    public static function resources(): array
    {
        $resources = [];

        foreach (self::groups() as $groupKey => $group) {
            foreach ($group['resources'] ?? [] as $resource => $definition) {
                $actionOptions = self::normalizeActionOptions(
                    $definition['actions'] ?? null
                );

                $resources[$resource] = [
                    'label' => $definition['label'] ?? $resource,
                    'group' => $groupKey,
                    'scope' => $definition['scope'] ?? null,
                    'actions' => array_keys($actionOptions),
                    'action_options' => $actionOptions,
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
        return self::resources()[$resource]['action_options'] ?? [];
    }

    public static function isActivityTypeScoped(string $resource): bool
    {
        return in_array(
            self::resources()[$resource]['scope'] ?? null,
            ['activity_type'],
            true,
        );
    }


    public static function permissionNames(
        bool $includeFilamentAccess = true
    ): array {
        $permissions = $includeFilamentAccess
            ? ['filament.access']
            : [];

        foreach (self::resources() as $resource => $definition) {
            if (self::isActivityTypeScoped($resource)) {
                foreach (self::activityTypeIds() as $activityTypeId) {
                    foreach ($definition['actions'] as $action) {
                        $permissions[] = self::activityTypePermissionName(
                            $resource,
                            $activityTypeId,
                            $action,
                        );
                    }
                }

                continue;
            }

            foreach ($definition['actions'] as $action) {
                $permissions[] = "{$resource}.{$action}";
            }
        }

        return array_values(array_unique($permissions));
    }

    public static function fieldName(string $resource): string
    {
        return 'permissions_' . str_replace('-', '_', $resource);
    }

    public static function activityTypeFieldName(
        string $resource,
        int $activityTypeId,
    ): string {
        return self::fieldName($resource)
            . '_type_'
            . $activityTypeId;
    }


    public static function activityTypePermissionName(
        string $resource,
        int $activityTypeId,
        string $action,
    ): string {
        return "{$resource}.type.{$activityTypeId}.{$action}";
    }


    public static function activityTypeIds(): array
    {
        if (! Schema::hasTable('activity_types')) {
            return [];
        }

        return ActivityType::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }


    private static function normalizeActionOptions(
        ?array $configuredActions
    ): array {
        if ($configuredActions === null) {
            return self::actions();
        }

        if (array_is_list($configuredActions)) {
            return array_intersect_key(
                self::actions(),
                array_flip($configuredActions)
            );
        }

        return $configuredActions;
    }
}
