<?php

namespace App\Policies;

use App\Models\CommunityRaffle;
use App\Models\User;

class CommunityRafflePolicy
{
    private function allowed(User $user): bool
    {
        return $user->hasRole('admin') || $user->can('raffles.manage');
    }

    public function viewAny(User $user): bool { return $this->allowed($user); }
    public function view(User $user, CommunityRaffle $raffle): bool { return $this->allowed($user); }
    public function create(User $user): bool { return $this->allowed($user); }
    public function update(User $user, CommunityRaffle $raffle): bool { return $this->allowed($user); }
    public function delete(User $user, CommunityRaffle $raffle): bool
    {
        return $this->allowed($user) && $raffle->status === 'draft' && ! $raffle->draws()->exists();
    }
    public function deleteAny(User $user): bool { return false; }
}
