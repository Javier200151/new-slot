<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

class AuditLogPolicy
{
    public function viewAny(
        User $user
    ): bool {
        return $user->can(
            'audit-log.view'
        );
    }

    public function view(
        User $user,
        Model $activity
    ): bool {
        return $user->can(
            'audit-log.view'
        );
    }

    public function create(
        User $user
    ): bool {
        return false;
    }

    public function update(
        User $user,
        Model $activity
    ): bool {
        return false;
    }

    public function delete(
        User $user,
        Model $activity
    ): bool {
        return false;
    }

    public function deleteAny(
        User $user
    ): bool {
        return false;
    }

    public function forceDelete(
        User $user,
        Model $activity
    ): bool {
        return false;
    }

    public function forceDeleteAny(
        User $user
    ): bool {
        return false;
    }

    public function restore(
        User $user,
        Model $activity
    ): bool {
        return false;
    }

    public function restoreAny(
        User $user
    ): bool {
        return false;
    }
}