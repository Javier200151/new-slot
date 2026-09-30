<?php

namespace App\Policies;

use App\Models\RecruitmentPeriod;
use App\Models\User;

class RecruitmentPeriodPolicy
{
    private function canAccess(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'recruitment-area.access');
    }

    public function viewAny(User $user): bool
    {
        return $this->canAccess($user);
    }

    public function view(User $user, RecruitmentPeriod $period): bool
    {
        return $this->canAccess($user);
    }

    public function create(User $user): bool
    {
        return false;
    }

    public function update(User $user, RecruitmentPeriod $period): bool
    {
        return $this->canAccess($user) && $period->isOpen();
    }

    public function delete(User $user, RecruitmentPeriod $period): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
