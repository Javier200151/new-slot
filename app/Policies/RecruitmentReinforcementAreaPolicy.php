<?php

namespace App\Policies;

use App\Models\RecruitmentReinforcementArea;
use App\Models\User;

class RecruitmentReinforcementAreaPolicy
{
    private function canAccess(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'recruitment-area.access');
    }

    public function viewAny(User $user): bool { return $this->canAccess($user); }
    public function view(User $user, RecruitmentReinforcementArea $record): bool { return $this->canAccess($user); }
    public function create(User $user): bool { return $this->canAccess($user); }
    public function update(User $user, RecruitmentReinforcementArea $record): bool { return $this->canAccess($user); }
    public function delete(User $user, RecruitmentReinforcementArea $record): bool
    {
        return $this->canAccess($user) && $record->deletionBlockReason() === null;
    }
    public function deleteAny(User $user): bool { return false; }
}
