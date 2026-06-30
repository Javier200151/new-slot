<?php

namespace App\Policies;

use App\Models\Promo;
use App\Models\User;

class PromoPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('promos.view')
            || $user->can('promos.create')
            || $user->can('promos.update')
            || $user->can('promos.delete');
    }

    public function view(User $user, Promo $promo): bool
    {
        return $user->can('promos.view');
    }

    public function create(User $user): bool
    {
        return $user->can('promos.create');
    }

    public function update(User $user, Promo $promo): bool
    {
        return $user->can('promos.update');
    }

    public function delete(User $user, Promo $promo): bool
    {
        return $user->can('promos.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('promos.delete');
    }

    public function restore(User $user, Promo $promo): bool
    {
        return $user->can('promos.update');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('promos.update');
    }

    public function forceDelete(User $user, Promo $promo): bool
    {
        return $user->can('promos.delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('promos.delete');
    }
}