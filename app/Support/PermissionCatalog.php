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
            ['activity_type', 'operation_type'],
            true,
        );
    }

    /** Alias histórico durante la transición. */
    public static function isOperationTypeScoped(string $resource): bool
    {
        return self::isActivityTypeScoped($resource);
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

    /** Alias histórico durante la transición. */
    public static function operationTypeFieldName(
        string $resource,
        int $operationTypeId,
    ): string {
        return self::activityTypeFieldName($resource, $operationTypeId);
    }

    public static function activityTypePermissionName(
        string $resource,
        int $activityTypeId,
        string $action,
    ): string {
        // El formato del permiso se conserva durante esta fase para mantener
        // exactamente los mismos IDs y asignaciones de Spatie.
        return "{$resource}.type.{$activityTypeId}.{$action}";
    }

    /** Alias histórico durante la transición. */
    public static function operationTypePermissionName(
        string $resource,
        int $operationTypeId,
        string $action,
    ): string {
        return self::activityTypePermissionName(
            $resource,
            $operationTypeId,
            $action,
        );
    }

    public static function activityTypeIds(): array
    {
        if (! Schema::hasTable('operations_type')) {
            return [];
        }

        return ActivityType::query()
            ->orderBy('id')
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    /** Alias histórico durante la transición. */
    public static function operationTypeIds(): array
    {
        return self::activityTypeIds();
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
