<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Spatie\Permission\Models\Role as SpatieRole;

class Role extends SpatieRole
{
    use Auditable;

    protected static function booted(): void
    {
        static::updating(function (self $role): void {
            if ($role->getOriginal('name') === 'admin') {
                throw new \Illuminate\Auth\Access\AuthorizationException('El rol admin está protegido.');
            }
        });

        static::deleting(function (self $role): void {
            if ($role->name === 'admin') {
                throw new \Illuminate\Auth\Access\AuthorizationException('El rol admin no se puede eliminar.');
            }
        });
    }

    /** El rol admin siempre conserva todos los permisos del guard. */
    public function syncPermissions(...$permissions): static
    {
        if ($this->name === 'admin') {
            $permissions = [\App\Models\Permission::query()
                ->where('guard_name', $this->guard_name)
                ->get()];
        }

        return parent::syncPermissions(...$permissions);
    }

    public function revokePermissionTo($permission): static
    {
        if ($this->name === 'admin') {
            throw new \Illuminate\Auth\Access\AuthorizationException('No se pueden retirar permisos del rol admin.');
        }

        return parent::revokePermissionTo($permission);
    }
}