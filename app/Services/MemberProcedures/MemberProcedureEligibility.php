<?php

namespace App\Services\MemberProcedures;

use App\Models\ContactSubmission;
use App\Models\MemberProcedure;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use LogicException;

class MemberProcedureEligibility
{
    /** @return array<int, string> */
    public function options(string $type): array
    {
        return $this->query($type)
            ->with('status')
            ->orderBy('nick')
            ->get()
            ->mapWithKeys(function (User $user): array {
                $status = mb_strtoupper(trim((string) $user->status?->name));
                $email = trim((string) $user->email);
                $parts = array_filter([
                    $user->nick ?: ('Usuario #' . $user->id),
                    $email !== '' ? $email : null,
                    $status !== '' ? $status : null,
                ]);

                return [$user->id => implode(' · ', $parts)];
            })
            ->all();
    }

    public function query(string $type): Builder
    {
        $query = User::query()
            ->whereDoesntHave('memberProcedures', fn (Builder $query) => $query
                ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR]));

        return match ($type) {
            MemberProcedure::TYPE_RECRUITMENT_START => $this->withStatuses($query, ['USUARIO'])
                ->whereIn('id', $this->approvedRecruitmentUserIds(requireRecruited: false)),

            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => $this->withStatuses($query, ['RECLUTA'])
                ->whereIn('id', $this->approvedRecruitmentUserIds()),

            MemberProcedure::TYPE_NOT_PROMOTED => $this->withStatuses($query, ['RECLUTA']),

            MemberProcedure::TYPE_RESERVE => $this->withStatuses($query, ['ACTIVO']),
            MemberProcedure::TYPE_REACTIVATION => $this->withStatuses($query, ['RESERVA']),

            MemberProcedure::TYPE_DEPARTURE,
            MemberProcedure::TYPE_DISMISSAL => $this->withStatuses($query, ['ACTIVO', 'RESERVA']),

            default => $query->whereRaw('1 = 0'),
        };
    }

    public function assertEligible(User $user, string $type): void
    {
        if ($this->query($type)->whereKey($user->getKey())->exists()) {
            return;
        }

        $user->loadMissing('status');
        $status = mb_strtoupper(trim((string) $user->status?->name)) ?: 'SIN ESTADO';
        $label = MemberProcedure::typeLabels()[$type] ?? $type;

        throw new LogicException(
            $user->nick . ' (' . $status . ') no es elegible para ejecutar «' . $label . '». '
            . 'Selecciona un usuario de la lista de candidatos del procedimiento.'
        );
    }

    private function withStatuses(Builder $query, array $statuses): Builder
    {
        return $query->whereHas('status', fn (Builder $query) => $query
            ->whereIn('name', $statuses));
    }

    /** @return array<int, int> */
    private function approvedRecruitmentUserIds(bool $requireRecruited = true): array
    {
        return ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->whereNotNull('recruitment_matched_user_id')
            ->when(! $requireRecruited, fn (Builder $query) => $query->whereNull('recruited_at'))
            ->pluck('recruitment_matched_user_id')
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }
}
