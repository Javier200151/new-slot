<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        $guard = PermissionCatalog::guard();

        $permission = Permission::firstOrCreate([
            'name' => 'recruitment-form.manage',
            'guard_name' => $guard,
        ]);

        // El administrador conserva todos los permisos. El resto de los roles
        // no recibe este permiso hasta que se le conceda expresamente.
        $admin = Role::firstOrCreate([
            'name' => 'admin',
            'guard_name' => $guard,
        ]);

        $admin->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        Permission::query()
            ->where('name', 'recruitment-form.manage')
            ->where('guard_name', PermissionCatalog::guard())
            ->delete();

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
};
