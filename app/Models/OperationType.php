<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use App\Models\Permission;
use App\Models\Role;
use App\Support\PermissionCatalog;
use Spatie\Permission\PermissionRegistrar;
use Illuminate\Support\Facades\Schema;

class OperationType extends Model
{
    use Auditable;
    protected $table = 'operations_type';

    public $timestamps = false;

    protected $fillable = [
        'name',
        'description',
        'oficial',
        'color',
    ];



    protected static function booted(): void
    {
        static::created(function (OperationType $operationType): void {
            if (
                ! Schema::hasTable('permissions')
                || ! Schema::hasTable('roles')
                || ! Schema::hasTable('role_has_permissions')
            ) {
                return;
            }

            $guard = PermissionCatalog::guard();
            $permissionNames = [];

            foreach (['operations', 'events'] as $resource) {
                foreach (PermissionCatalog::actionsFor($resource) as $action) {
                    $permissionName =
                        PermissionCatalog::operationTypePermissionName(
                            $resource,
                            (int) $operationType->id,
                            $action,
                        );

                    Permission::firstOrCreate([
                        'name' => $permissionName,
                        'guard_name' => $guard,
                    ]);

                    $permissionNames[] = $permissionName;
                }
            }

            $adminRole = Role::query()
                ->where('name', 'admin')
                ->where('guard_name', $guard)
                ->first();

            if ($adminRole && $permissionNames !== []) {
                $adminRole->givePermissionTo($permissionNames);
            }

            app(PermissionRegistrar::class)
                ->forgetCachedPermissions();
        });
    }

    protected function casts(): array
    {
        return [
            'oficial' => 'boolean',
        ];
    }
}
