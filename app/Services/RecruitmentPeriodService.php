<?php

namespace App\Services;

use App\Models\RecruitmentPeriod;
use App\Models\RecruitmentReentryReview;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class RecruitmentPeriodService
{
    public function __construct(
        private readonly RecruitmentParticipationService $participation,
    ) {
    }

    public function handleCreatedUser(User $user): void
    {
        if ($this->statusName($user->status_id) !== 'RECLUTA') {
            return;
        }

        $this->enterRecruitment($user, 'user_created');
    }

    public function handleStatusTransition(User $user, int $previousStatusId): void
    {
        $previousStatusName = $this->statusName($previousStatusId);
        $currentStatusName = $this->statusName($user->status_id);

        if ($previousStatusName === $currentStatusName) {
            return;
        }

        if ($currentStatusName === 'RECLUTA') {
            $this->enterRecruitment($user, 'status_transition');

            return;
        }

        if ($previousStatusName === 'RECLUTA') {
            $this->leaveRecruitment($user, $currentStatusName);
        }
    }

    public function syncLegacyTutorChange(User $user): void
    {
        if ($this->statusName($user->status_id) !== 'RECLUTA') {
            return;
        }

        RecruitmentPeriod::query()
            ->where('open_user_id', $user->id)
            ->update([
                'tutor_id' => $user->tutor_id,
                'process_status' => $user->tutor_id
                    ? RecruitmentPeriod::PROCESS_IN_PROGRESS
                    : RecruitmentPeriod::PROCESS_PENDING_TUTOR,
                'updated_at' => now(),
            ]);
    }


    public function markPromotionPending(RecruitmentPeriod $period, ?int $actorId = null): void
    {
        if (! $period->isOpen()) {
            throw new \LogicException('Solo puede marcarse un periodo abierto.');
        }

        $period->update([
            'process_status' => RecruitmentPeriod::PROCESS_PENDING_PROMOTION,
            'promotion_pending_at' => now(),
            'promotion_pending_by' => $actorId ?? Auth::id(),
        ]);
    }

    public function clearPromotionPending(RecruitmentPeriod $period): void
    {
        if (! $period->isOpen()) {
            throw new \LogicException('Solo puede modificarse un periodo abierto.');
        }

        $period->update([
            'process_status' => $period->tutor_id
                ? RecruitmentPeriod::PROCESS_IN_PROGRESS
                : RecruitmentPeriod::PROCESS_PENDING_TUTOR,
            'promotion_pending_at' => null,
            'promotion_pending_by' => null,
        ]);
    }

    public function resolvePromotedReentryAsStatusError(
        RecruitmentReentryReview $review,
        ?int $resolvedByUserId = null,
    ): void {
        DB::transaction(function () use ($review, $resolvedByUserId): void {
            $locked = RecruitmentReentryReview::query()
                ->lockForUpdate()
                ->findOrFail($review->id);

            if (! $locked->isPending()) {
                return;
            }

            $locked->update([
                'pending_user_id' => null,
                'resolved_at' => now(),
                'resolution' => RecruitmentReentryReview::RESOLUTION_STATUS_CHANGE_ERROR,
                'resolved_by_user_id' => $resolvedByUserId ?? Auth::id(),
                'resolution_note' => 'Reincorporación descartada manualmente: cambio de status considerado administrativo/erróneo.',
            ]);
        });
    }

    /**
     * Confirma manualmente una reincorporación que anteriormente terminó
     * PROMOCIONADA. La futura UI de tutores llamará a este método.
     */
    public function confirmPromotedReentry(
        RecruitmentReentryReview $review,
        ?int $resolvedByUserId = null,
    ): RecruitmentPeriod {
        return DB::transaction(function () use ($review, $resolvedByUserId): RecruitmentPeriod {
            $locked = RecruitmentReentryReview::query()
                ->lockForUpdate()
                ->findOrFail($review->id);

            if (! $locked->isPending()) {
                return RecruitmentPeriod::query()
                    ->where('open_user_id', $locked->user_id)
                    ->firstOrFail();
            }

            $user = User::withTrashed()->lockForUpdate()->findOrFail($locked->user_id);

            if ($this->statusName($user->status_id) !== 'RECLUTA') {
                throw new \LogicException('El usuario ya no está en estado RECLUTA.');
            }

            $period = $this->createPeriod($user, 'confirmed_reentry');

            $locked->update([
                'pending_user_id' => null,
                'resolved_at' => now(),
                'resolution' => RecruitmentReentryReview::RESOLUTION_NEW_PERIOD_STARTED,
                'resolved_by_user_id' => $resolvedByUserId ?? Auth::id(),
            ]);

            return $period;
        });
    }

    private function enterRecruitment(User $user, string $startedAtSource): void
    {
        DB::transaction(function () use ($user, $startedAtSource): void {
            $lockedUser = User::withTrashed()->lockForUpdate()->findOrFail($user->id);

            if ($this->statusName($lockedUser->status_id) !== 'RECLUTA') {
                return;
            }

            if (RecruitmentPeriod::query()->where('open_user_id', $lockedUser->id)->exists()) {
                return;
            }

            if (RecruitmentReentryReview::query()->where('pending_user_id', $lockedUser->id)->exists()) {
                return;
            }

            $lastPeriod = RecruitmentPeriod::query()
                ->where('user_id', $lockedUser->id)
                ->orderByDesc('period_number')
                ->lockForUpdate()
                ->first();

            if ($lastPeriod?->result === RecruitmentPeriod::RESULT_PROMOTED) {
                RecruitmentReentryReview::query()->create([
                    'user_id' => $lockedUser->id,
                    'pending_user_id' => $lockedUser->id,
                    'previous_period_id' => $lastPeriod->id,
                    'detected_at' => now(),
                ]);

                return;
            }

            $this->createPeriod($lockedUser, $startedAtSource, $lastPeriod);
        });
    }

    private function createPeriod(
        User $user,
        string $startedAtSource,
        ?RecruitmentPeriod $lastPeriod = null,
    ): RecruitmentPeriod {
        $lastPeriod ??= RecruitmentPeriod::query()
            ->where('user_id', $user->id)
            ->orderByDesc('period_number')
            ->lockForUpdate()
            ->first();

        /*
         * users.tutor_id es un campo legacy. En un periodo NUEVO no debemos
         * arrastrar al tutor del intento anterior. Se limpia silenciosamente
         * porque el tutor canónico pertenece al RecruitmentPeriod.
         */
        if ($user->tutor_id !== null) {
            DB::table('users')
                ->where('id', $user->id)
                ->update([
                    'tutor_id' => null,
                    'updated_at' => now(),
                ]);
        }

        $startedAt = match ($startedAtSource) {
            'status_transition' => $user->updated_at ?? now(),
            'user_created' => $user->created_at ?? now(),
            default => now(),
        };

        return RecruitmentPeriod::query()->create([
            'user_id' => $user->id,
            'open_user_id' => $user->id,
            'period_number' => ((int) ($lastPeriod?->period_number ?? 0)) + 1,
            // Todo nuevo periodo empieza limpio, incluido un antiguo tutor legacy.
            'tutor_id' => null,
            'process_status' => RecruitmentPeriod::PROCESS_PENDING_TUTOR,
            'result' => null,
            'tutorials_status' => RecruitmentPeriod::TUTORIALS_NO,
            'diary_rating' => null,
            'official_events_allowed' => false,
            'current_note' => null,
            'promotion_pending_at' => null,
            'promotion_pending_by' => null,
            'started_at' => $startedAt,
            'started_at_source' => $startedAtSource,
        ]);
    }

    private function leaveRecruitment(User $user, string $finalStatusName): void
    {
        DB::transaction(function () use ($user, $finalStatusName): void {
            $lockedUser = User::withTrashed()->lockForUpdate()->findOrFail($user->id);

            $period = RecruitmentPeriod::query()
                ->where('open_user_id', $lockedUser->id)
                ->lockForUpdate()
                ->first();

            if (! $period) {
                $this->resolvePendingReentryBecauseStatusChanged($lockedUser);

                return;
            }

            $endedAt = now();
            $result = $finalStatusName === 'ACTIVO'
                ? RecruitmentPeriod::RESULT_PROMOTED
                : RecruitmentPeriod::RESULT_NOT_PROMOTED;

            $eventsPlayed = $period->started_at
                ? $this->participation->countPlayedEvents(
                    $lockedUser->id,
                    $period->started_at,
                    $endedAt,
                )
                : null;

            $period->update([
                'open_user_id' => null,
                'process_status' => RecruitmentPeriod::PROCESS_CLOSED,
                'result' => $result,
                'ended_at' => $endedAt,
                'final_status_id' => $lockedUser->status_id,
                'final_status_name' => $finalStatusName,
                'events_played_final' => $eventsPlayed,
                'user_nick_snapshot' => $lockedUser->nick,
                'tutor_nick_snapshot' => $period->tutor?->nick,
                'closed_by_user_id' => Auth::id(),
            ]);

            // El tutor queda congelado en el periodo cerrado, no en users.
            if ($lockedUser->tutor_id !== null) {
                DB::table('users')
                    ->where('id', $lockedUser->id)
                    ->update([
                        'tutor_id' => null,
                        'updated_at' => now(),
                    ]);
            }
        });
    }

    private function resolvePendingReentryBecauseStatusChanged(User $user): void
    {
        $review = RecruitmentReentryReview::query()
            ->where('pending_user_id', $user->id)
            ->lockForUpdate()
            ->first();

        if (! $review) {
            return;
        }

        $review->update([
            'pending_user_id' => null,
            'resolved_at' => now(),
            'resolution' => RecruitmentReentryReview::RESOLUTION_STATUS_CHANGE_ERROR,
            'resolved_by_user_id' => Auth::id(),
            'resolution_note' => 'El usuario dejó de estar en RECLUTA antes de iniciar un nuevo periodo.',
        ]);
    }

    private function statusName(?int $statusId): string
    {
        if (! $statusId) {
            return '';
        }

        return mb_strtoupper(trim((string) Status::withTrashed()->whereKey($statusId)->value('name')));
    }
}
