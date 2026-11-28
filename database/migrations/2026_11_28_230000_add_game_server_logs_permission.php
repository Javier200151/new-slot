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
            'name' => 'game-servers.logs',
            'guard_name' => $guard,
        ]);

        // Solo el rol admin recibe este permiso inicialmente. Los demás
        // roles pueden habilitarlo expresamente desde Filament → Roles.
        $admin = Role::query()
            ->where('name', 'admin')
            ->where('guard_name', $guard)
            ->first();

        $admin?->givePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // Conservar las concesiones ya asignadas si se revierte la migración.
    }
};
