<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use App\Support\CommunityForumCategory;
use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = PermissionCatalog::guard();
        $knownPermissionNames = PermissionCatalog::permissionNames();

        $newPermissionNames = [];

        foreach ($knownPermissionNames as $permissionName) {
            $permission = Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);

            if ($permission->wasRecentlyCreated) {
                $newPermissionNames[] = $permissionName;
            }
        }

        /*
        |--------------------------------------------------------------------------
        | Migración de permisos antiguos
        |--------------------------------------------------------------------------
        |
        | Antes existían permisos globales de actividades y
        | events.view/create/update/delete. En el primer despliegue copiamos cada
        | permiso antiguo a TODOS los tipos existentes para no quitar acceso a
        | los roles actuales. Después retiramos el permiso antiguo del rol.
        |
        | Una vez el rol se edite, podrá limitarse a CURSO, MANIOBRA, etc.
        */
        $activityTypeIds = PermissionCatalog::activityTypeIds();

        if ($activityTypeIds !== []) {
            Role::query()
                ->where('guard_name', $guard)
                ->with('permissions')
                ->each(function (Role $role) use ($activityTypeIds): void {
                    if ($role->name === 'admin') {
                        return;
                    }

                    foreach (PermissionCatalog::resources() as $resource => $definition) {
                        if (! PermissionCatalog::isActivityTypeScoped($resource)) {
                            continue;
                        }

                        foreach (PermissionCatalog::actionsFor($resource) as $action) {
                            $legacyPermissionName = "{$resource}.{$action}";

                            if (! $role->permissions->contains('name', $legacyPermissionName)) {
                                continue;
                            }

                            $scopedPermissionNames = array_map(
                                fn (int $activityTypeId): string =>
                                    PermissionCatalog::activityTypePermissionName(
                                        $resource,
                                        $activityTypeId,
                                        $action,
                                    ),
                                $activityTypeIds,
                            );

                            $role->givePermissionTo($scopedPermissionNames);
                            $role->revokePermissionTo($legacyPermissionName);
                        }
                    }
                });
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $adminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->whereIn('name', $knownPermissionNames)
                ->pluck('name')
                ->all()
        );

        $forumCategoryResources = array_keys(
            CommunityForumCategory::permissionResources()
        );

        /*
         * Los usuarios normales conservan la posibilidad de abrir hilos en
         * todas las categorías. Después puede retirarse categoría por categoría
         * desde Roles si se quiere restringir una de ellas.
         */
        $userRole = Role::query()
            ->where('name', 'user')
            ->where('guard_name', $guard)
            ->first();

        if ($userRole) {
            $forumUserPermissions = [];
            foreach ($forumCategoryResources as $resource) {
                foreach (['create', 'reply', 'poll'] as $action) {
                    $permissionName = "{$resource}.{$action}";

                    // Solo damos los valores por defecto cuando el permiso acaba
                    // de crearse. De este modo, una edición posterior desde Roles
                    // no se deshace al volver a ejecutar el seeder.
                    if (in_array($permissionName, $newPermissionNames, true)) {
                        $forumUserPermissions[] = $permissionName;
                    }
                }
            }

            if ($forumUserPermissions !== []) {
                $userRole->givePermissionTo($forumUserPermissions);
            }
        }

        $forumModeratorRole = Role::firstOrCreate([
            'name' => 'moderador foro',
            'guard_name' => $guard,
        ]);

        if ($forumModeratorRole->wasRecentlyCreated) {
            $forumModeratorPermissions = [];
            foreach ($forumCategoryResources as $resource) {
                $forumModeratorPermissions[] = "{$resource}.reply";
                $forumModeratorPermissions[] = "{$resource}.moderate";
                $forumModeratorPermissions[] = "{$resource}.delete";
            }
            $forumModeratorRole->givePermissionTo($forumModeratorPermissions);
        }

        /*
         * Migración transparente del permiso global utilizado en el bloque
         * anterior. Si un rol moderaba todo el foro, conserva esa capacidad en
         * todas las categorías al ejecutar de nuevo el seeder.
         */
        Role::query()
            ->where('guard_name', $guard)
            ->with('permissions')
            ->each(function (Role $role) use ($forumCategoryResources): void {
                $legacyModerate = $role->permissions->contains('name', 'community-forum.moderate');
                $legacyDelete = $role->permissions->contains('name', 'community-forum.delete');

                if ($legacyModerate) {
                    $role->givePermissionTo(array_map(
                        fn (string $resource): string => "{$resource}.moderate",
                        $forumCategoryResources,
                    ));
                    $role->revokePermissionTo('community-forum.moderate');
                }

                if ($legacyDelete) {
                    $role->givePermissionTo(array_map(
                        fn (string $resource): string => "{$resource}.delete",
                        $forumCategoryResources,
                    ));
                    $role->revokePermissionTo('community-forum.delete');
                }
            });

        $registrar->forgetCachedPermissions();
    }
}
