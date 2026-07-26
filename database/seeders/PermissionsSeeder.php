<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class PermissionsSeeder extends Seeder
{
    public function run(): void
    {
        app()[PermissionRegistrar::class]->forgetCachedPermissions();

        $guard = 'web';

        $resources = [
            'metopas',
            'user-metopas',
            'users',
            'promos',
            'statuses',
            'roles',
        ];

        $actions = [
            'view',
            'create',
            'update',
            'delete',
        ];

        Permission::firstOrCreate([
            'name' => 'filament.access',
            'guard_name' => $guard,
        ]);

        foreach ($resources as $resource) {
            foreach ($actions as $action) {
                Permission::firstOrCreate([
                    'name' => "{$resource}.{$action}",
                    'guard_name' => $guard,
                ]);
            }
        }

        $adminRole = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $adminRole->syncPermissions(
            Permission::query()
                ->where('guard_name', $guard)
                ->get()
        );

        app()[PermissionRegistrar::class]->forgetCachedPermissions();
    }
}