<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('permissions')) {
            Permission::findOrCreate('orbat.edit_ally_names', 'web');
            app(PermissionRegistrar::class)->forgetCachedPermissions();
        }
    }

    public function down(): void
    {
        // No revocar permisos asignados al hacer rollback.
    }
};
