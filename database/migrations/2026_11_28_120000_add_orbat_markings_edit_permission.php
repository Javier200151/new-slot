<?php

use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $guard = PermissionCatalog::guard();
        $permission = Permission::firstOrCreate([
            'name' => 'orbat-markings.edit',
            'guard_name' => $guard,
        ]);

        // El rol admin conserva el permiso por defecto. Los demás roles
        // lo reciben únicamente si se activa expresamente en Filament.
        $admin = Role::query()
            ->where('name', 'admin')->where('guard_name', $guard)->first();
        $admin?->givePermissionTo($permission);

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No revocar concesiones de los administradores durante un rollback.
    }
};
