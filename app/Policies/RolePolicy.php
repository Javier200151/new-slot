<?php

namespace App\Policies;

use App\Models\User;
use Spatie\Permission\Models\Role;

class RolePolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('roles.view')
            || $user->can('roles.create')
            || $user->can('roles.update')
            || $user->can('roles.delete');
    }

    public function view(User $user, Role $role): bool
    {
        return $user->can('roles.view');
    }

    public function create(User $user): bool
    {
        return $user->can('roles.create');
    }

    public function update(User $user, Role $role): bool
    {
        return $user->can('roles.update');
    }

    public function delete(User $user, Role $role): bool
    {
        if (in_array($role->name, ['Admin', 'Administrador'])) {
            return false;
        }

        return $user->can('roles.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('roles.delete');
    }
}