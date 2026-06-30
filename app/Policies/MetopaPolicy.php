<?php

namespace App\Policies;

use App\Models\Metopa;
use App\Models\User;

class MetopaPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('metopas.view')
            || $user->can('metopas.create')
            || $user->can('metopas.update')
            || $user->can('metopas.delete');
    }

    public function view(User $user, Metopa $metopa): bool
    {
        return $user->can('metopas.view');
    }

    public function create(User $user): bool
    {
        return $user->can('metopas.create');
    }

    public function update(User $user, Metopa $metopa): bool
    {
        return $user->can('metopas.update');
    }

    public function delete(User $user, Metopa $metopa): bool
    {
        return $user->can('metopas.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('metopas.delete');
    }

    public function restore(User $user, Metopa $metopa): bool
    {
        return $user->can('metopas.update');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('metopas.update');
    }

    public function forceDelete(User $user, Metopa $metopa): bool
    {
        return $user->can('metopas.delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('metopas.delete');
    }
}