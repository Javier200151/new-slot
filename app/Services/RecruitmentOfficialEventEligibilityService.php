<?php

namespace App\Services;

use App\Models\Event;
use App\Models\RecruitmentReentryReview;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecruitmentOfficialEventEligibilityService
{
    public function hasPendingReserveRetutoring(User $user): bool
    {
        return RecruitmentReentryReview::query()
            ->where('pending_user_id', $user->id)
            ->where('review_type', RecruitmentReentryReview::TYPE_RESERVE_TO_ACTIVE)
            ->whereNull('resolved_at')
            ->exists();
    }

    public function assertCanSelfRegister(User $user, Event $event, string $field = 'slot'): void
    {
        if ($this->hasPendingReserveRetutoring($user)) {
            throw ValidationException::withMessages([
                $field => 'Tu reincorporación desde reserva sigue pendiente de aprobación. Hasta que Tutores apruebe la retutoría, o confirme que no es necesaria, no puedes apuntarte ni reservarte en ningún evento.',
            ]);
        }

        $user->loadMissing(['status', 'currentRecruitmentPeriod']);

        if (mb_strtoupper(trim((string) $user->status?->name)) !== 'RECLUTA') {
            return;
        }

        $period = $user->currentRecruitmentPeriod;
        if (! $period || $period->official_events_allowed) {
            return;
        }

        $event->loadMissing('activity.days');
        $dayNames = $event->activity?->days
            ?->pluck('name')
            ->map(fn ($name): string => mb_strtoupper(trim((string) $name)))
            ->all() ?? [];

        if (! array_intersect($dayNames, ['MARTES', 'VIERNES'])) {
            return;
        }

        throw ValidationException::withMessages([
            $field => 'Tu periodo de reclutamiento todavía no permite apuntarte a partidas oficiales de martes o viernes.',
        ]);
    }
}
