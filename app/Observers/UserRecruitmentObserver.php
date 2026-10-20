<?php

namespace App\Observers;

use App\Models\User;
use App\Services\RecruitmentApplicationService;
use App\Services\RecruitmentPeriodService;
use App\Services\FirstRecruitmentHistoryService;
use App\Services\FirstMembershipHistoryService;
use App\Services\VeterancyStatusHistoryService;
use App\Models\Status;
use App\Services\Wiki\WikiSessionRevoker;

class UserRecruitmentObserver
{
    public function __construct(
        private readonly RecruitmentPeriodService $recruitmentPeriods,
        private readonly RecruitmentApplicationService $recruitmentApplications,
        private readonly VeterancyStatusHistoryService $veterancyHistory,
    ) {
    }

    public function created(User $user): void
    {
        $this->recruitmentPeriods->handleCreatedUser($user);
        $this->recruitmentApplications->syncUser($user);
        $this->recruitmentApplications->markRecruitmentStarted($user);
        // Fecha inicial documentada (no altera las transiciones ni la veteranía).
        app(FirstRecruitmentHistoryService::class)->syncUser($user);
        app(FirstMembershipHistoryService::class)->syncUser($user);
    }

    public function updated(User $user): void
    {
        if ($user->wasChanged('status_id')) {
            // Only ACTIVO, RESERVA and RECLUTA may retain authenticated Wiki sessions.
            $newStatus = mb_strtoupper(trim((string) Status::withTrashed()->whereKey($user->status_id)->value('name')));
            if (!in_array($newStatus, ['ACTIVO', 'RESERVA', 'RECLUTA'], true)) {
                app(WikiSessionRevoker::class)->record($user);
            }

            $previousStatusId = (int) ($user->getPrevious()['status_id'] ?? 0);

            $this->veterancyHistory->handleTransition($user, $previousStatusId);

            $this->recruitmentPeriods->handleStatusTransition(
                $user,
                $previousStatusId,
            );

            $this->recruitmentApplications->markRecruitmentStarted($user);
        }

        if ($user->wasChanged('email')) {
            $this->recruitmentApplications->syncUser($user);
        }

        /*
         * Compatibilidad temporal con users.tutor_id mientras la interfaz
         * antigua siga existiendo. El dato canónico del proceso es el periodo.
         */
        if ($user->wasChanged('tutor_id')) {
            $this->recruitmentPeriods->syncLegacyTutorChange($user);
        }

        // Registrar primero el cambio real (arriba). Las fechas históricas
        // solo generan anotaciones documentadas si ese cambio no existe.
        // Se sincronizan además cuando Filament edita «Miembro desde».
        if ($user->wasChanged('recruited_at') || $user->wasChanged('member_at')) {
            app(FirstRecruitmentHistoryService::class)->syncUser($user);
        }

        if ($user->wasChanged('status_id') || $user->wasChanged('member_at') || $user->wasChanged('recruited_at')) {
            app(FirstMembershipHistoryService::class)->syncUser($user);
        }
    }
}
