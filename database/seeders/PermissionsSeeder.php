<?php

namespace Database\Seeders;

use App\Support\PermissionCatalog;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        $registrar = app(PermissionRegistrar::class);
        $registrar->forgetCachedPermissions();

        $guard = PermissionCatalog::guard();

        foreach (PermissionCatalog::permissionNames() as $permissionName) {
            Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => $guard,
            ]);
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $adminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->pluck('name')
                ->all()
        );

        $registrar->forgetCachedPermissions();
    }
}