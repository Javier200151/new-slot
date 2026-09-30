<?php

namespace App\Policies;

use App\Models\RecruitmentReentryReview;
use App\Models\User;

class RecruitmentReentryReviewPolicy
{
    private function canAssign(User $user): bool
    {
        return $user->getAllPermissions()->contains('name', 'recruitment-area.assign');
    }

    public function viewAny(User $user): bool { return $this->canAssign($user); }
    public function view(User $user, RecruitmentReentryReview $record): bool { return $this->canAssign($user); }
    public function create(User $user): bool { return false; }
    public function update(User $user, RecruitmentReentryReview $record): bool { return $this->canAssign($user) && $record->isPending(); }
    public function delete(User $user, RecruitmentReentryReview $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
