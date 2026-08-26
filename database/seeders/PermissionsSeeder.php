<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
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

        foreach ($knownPermissionNames as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Migración de permisos antiguos
        |--------------------------------------------------------------------------
        |
        | Antes existían operations.view/create/update/delete y
        | events.view/create/update/delete. En el primer despliegue copiamos cada
        | permiso antiguo a TODOS los tipos existentes para no quitar acceso a
        | los roles actuales. Después retiramos el permiso antiguo del rol.
        |
        | Una vez el rol se edite, podrá limitarse a CURSO, MANIOBRA, etc.
        */
        $operationTypeIds = PermissionCatalog::operationTypeIds();

        if ($operationTypeIds !== []) {
            Role::query()
                ->where('guard_name', $guard)
                ->with('permissions')
                ->each(function (Role $role) use ($operationTypeIds): void {
                    if ($role->name === 'admin') {
                        return;
                    }

                    foreach (['operations', 'events'] as $resource) {
                        foreach (PermissionCatalog::actionsFor($resource) as $action) {
                            $legacyPermissionName = "{$resource}.{$action}";

                            if (! $role->permissions->contains('name', $legacyPermissionName)) {
                                continue;
                            }

                            $scopedPermissionNames = array_map(
                                fn (int $operationTypeId): string =>
                                    PermissionCatalog::operationTypePermissionName(
                                        $resource,
                                        $operationTypeId,
                                        $action,
                                    ),
                                $operationTypeIds,
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

        $registrar->forgetCachedPermissions();
    }
}
