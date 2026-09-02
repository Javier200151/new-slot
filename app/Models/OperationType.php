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
        'uses_enemy_factions',
        'uses_event_result',
        'supports_ocap',
        'supports_respawn',
        'supports_jip',
        'awards_metopa',
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

            foreach (PermissionCatalog::resources() as $resource => $definition) {
                if (! PermissionCatalog::isOperationTypeScoped($resource)) {
                    continue;
                }

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

    public function operations()
    {
        return $this->hasMany(Operation::class, 'operation_type_id');
    }

    public function usesEnemyFactions(): bool
    {
        return (bool) $this->uses_enemy_factions;
    }

    public function usesEventResult(): bool
    {
        return (bool) $this->uses_event_result;
    }

    public function supportsOcap(): bool
    {
        return (bool) $this->supports_ocap;
    }

    public function supportsRespawn(): bool
    {
        return (bool) $this->supports_respawn;
    }

    public function supportsJip(): bool
    {
        return (bool) $this->supports_jip;
    }

    public function awardsMetopa(): bool
    {
        return (bool) $this->awards_metopa;
    }

    protected function casts(): array
    {
        return [
            'oficial' => 'boolean',
            'uses_enemy_factions' => 'boolean',
            'uses_event_result' => 'boolean',
            'supports_ocap' => 'boolean',
            'supports_respawn' => 'boolean',
            'supports_jip' => 'boolean',
            'awards_metopa' => 'boolean',
        ];
    }
}
