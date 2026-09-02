<?php

namespace App\Policies;

use App\Models\CommunityPoll;
use App\Models\User;

class CommunityPollPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, CommunityPoll $poll): bool
    {
        return $user->hasRole('admin');
    }

    public function create(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function update(User $user, CommunityPoll $poll): bool
    {
        return $user->hasRole('admin');
    }

    public function delete(User $user, CommunityPoll $poll): bool
    {
        return $user->hasRole('admin');
    }

    public function deleteAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function restore(User $user, CommunityPoll $poll): bool
    {
        return $user->hasRole('admin');
    }

    public function forceDelete(User $user, CommunityPoll $poll): bool
    {
        return $user->hasRole('admin');
    }
}
