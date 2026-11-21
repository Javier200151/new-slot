<?php

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('permissions') || ! Schema::hasTable('roles')) {
            return;
        }

        $permissions = ['game-servers.view', 'game-servers.launch', 'game-servers.stop'];

        foreach ($permissions as $name) {
            Permission::firstOrCreate(['name' => $name, 'guard_name' => 'web']);
        }

        // Rol asignable desde Filament. No concede derechos automáticamente a GOP/GET.
        $role = Role::firstOrCreate(['name' => 'Gestion de servidores', 'guard_name' => 'web']);

        if ($role->wasRecentlyCreated) {
            $role->givePermissionTo($permissions);
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function down(): void
    {
        // No se eliminan los permisos ni el rol si ya se han asignado a usuarios.
    }
};
