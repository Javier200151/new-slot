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

                $actionOptions = self::normalizeActionOptions(
                    $definition['actions'] ?? null
                );

                $resources[$resource] = [
                    'label' => $definition['label'] ?? $resource,
                    'group' => $groupKey,

                    /*
                     * Lista interna de acciones:
                     *
                     * ['view', 'create', 'update', 'delete']
                     *
                     * o:
                     *
                     * ['manage']
                     */
                    'actions' => array_keys($actionOptions),

                    /*
                     * Opciones que verá Filament:
                     *
                     * [
                     *     'manage' => 'Manejar ORBAT',
                     * ]
                     */
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
        return 'permissions_' . str_replace(
            '-',
            '_',
            $resource
        );
    }

    private static function normalizeActionOptions(
        ?array $configuredActions
    ): array {
        /*
         * Sin configuración específica:
         * usamos las acciones CRUD normales.
         */
        if ($configuredActions === null) {
            return self::actions();
        }

        /*
         * Ejemplo:
         *
         * 'actions' => ['view']
         *
         * Busca las etiquetas en el catálogo general.
         */
        if (array_is_list($configuredActions)) {
            return array_intersect_key(
                self::actions(),
                array_flip($configuredActions)
            );
        }

        /*
         * Permite acciones personalizadas:
         *
         * 'actions' => [
         *     'manage' => 'Manejar ORBAT',
         * ]
         */
        return $configuredActions;
    }
}