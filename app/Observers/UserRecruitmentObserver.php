<?php

namespace App\Observers;

use App\Models\User;
use App\Services\RecruitmentPeriodService;

class UserRecruitmentObserver
{
    public function __construct(
        private readonly RecruitmentPeriodService $recruitmentPeriods,
    ) {
    }

    public function created(User $user): void
    {
        $this->recruitmentPeriods->handleCreatedUser($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('status_id')) {
            $this->recruitmentPeriods->handleStatusTransition(
                $user,
                (int) ($user->getPrevious()['status_id'] ?? 0),
            );
        }

        /*
         * Compatibilidad temporal con users.tutor_id mientras la interfaz
         * antigua siga existiendo. El dato canónico del proceso es el periodo.
         */
        if ($user->wasChanged('tutor_id')) {
            $this->recruitmentPeriods->syncLegacyTutorChange($user);
        }
    }
}
