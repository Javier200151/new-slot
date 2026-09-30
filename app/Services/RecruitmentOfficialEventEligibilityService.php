<?php

namespace App\Services;

use App\Models\Event;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class RecruitmentOfficialEventEligibilityService
{
    public function assertCanSelfRegister(User $user, Event $event, string $field = 'slot'): void
    {
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
