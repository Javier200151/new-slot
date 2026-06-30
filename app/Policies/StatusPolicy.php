<?php

namespace App\Policies;

use App\Models\Status;
use App\Models\User;

class StatusPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->can('statuses.view')
            || $user->can('statuses.create')
            || $user->can('statuses.update')
            || $user->can('statuses.delete');
    }

    public function view(User $user, Status $status): bool
    {
        return $user->can('statuses.view');
    }

    public function create(User $user): bool
    {
        return $user->can('statuses.create');
    }

    public function update(User $user, Status $status): bool
    {
        return $user->can('statuses.update');
    }

    public function delete(User $user, Status $status): bool
    {
        return $user->can('statuses.delete');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('statuses.delete');
    }

    public function restore(User $user, Status $status): bool
    {
        return $user->can('statuses.update');
    }

    public function restoreAny(User $user): bool
    {
        return $user->can('statuses.update');
    }

    public function forceDelete(User $user, Status $status): bool
    {
        return $user->can('statuses.delete');
    }

    public function forceDeleteAny(User $user): bool
    {
        return $user->can('statuses.delete');
    }
}