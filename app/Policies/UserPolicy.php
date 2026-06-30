<?php

namespace App\Policies;

use App\Models\User;

class UserPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('users.view')
            || $user->can('users.create')
            || $user->can('users.update')
            || $user->can('users.delete');
    }

    public function view(User $user, User $targetUser): bool
    {
        return $user->can('users.view');
    }

    public function create(User $user): bool
    {
        return $user->can('users.create');
    }

    public function update(User $user, User $targetUser): bool
    {
        return $user->can('users.update');
    }

    public function delete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->can('users.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }

    public function restore(User $user, User $targetUser): bool
    {
        return $user->can('users.update');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('users.update');
    }

    public function forceDelete(User $user, User $targetUser): bool
    {
        if ($user->id === $targetUser->id) {
            return false;
        }

        return $user->can('users.delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('users.delete');
    }
}