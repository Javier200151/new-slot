<?php

namespace App\Services;

use App\Models\ContactSubmission;
use App\Models\RecruitmentApplicationTierRating;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class RecruitmentApplicationService
{
    public function approve(ContactSubmission $submission, ?int $reviewerUserId): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        return DB::transaction(function () use ($submission, $reviewerUserId): ContactSubmission {
            $submission->loadMissing('recruitmentMatchedUser.status');
            $matchedUser = $submission->recruitmentMatchedUser
                ?? $this->findMatchingUser($submission->email);

            $submission->forceFill([
                'recruitment_review_status' => ContactSubmission::REVIEW_APPROVED,
                'recruitment_reviewed_at' => now(),
                'recruitment_reviewed_by' => $reviewerUserId,
                'recruitment_matched_user_id' => $matchedUser?->id,
                'recruited_at' => $matchedUser && $this->isRecruit($matchedUser)
                    ? ($submission->recruited_at ?? now())
                    : $submission->recruited_at,
                'read_at' => $submission->read_at ?? now(),
            ])->save();

            return $submission->fresh([
                'recruitmentReviewedBy',
                'recruitmentInterviewer',
                'recruitmentMatchedUser.status',
                'recruitmentTierRatings.user',
            ]);
        });
    }

    public function discard(ContactSubmission $submission, ?int $reviewerUserId): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        $submission->forceFill([
            'recruitment_review_status' => ContactSubmission::REVIEW_DISCARDED,
            'recruitment_reviewed_at' => now(),
            'recruitment_reviewed_by' => $reviewerUserId,
            'read_at' => $submission->read_at ?? now(),
        ])->save();

        return $submission->fresh([
            'recruitmentReviewedBy',
            'recruitmentInterviewer',
            'recruitmentMatchedUser.status',
            'recruitmentTierRatings.user',
        ]);
    }

    public function resetDecision(ContactSubmission $submission): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        $submission->forceFill([
            'recruitment_review_status' => ContactSubmission::REVIEW_UNREVIEWED,
            'recruitment_reviewed_at' => null,
            'recruitment_reviewed_by' => null,
        ])->save();

        return $submission->fresh([
            'recruitmentReviewedBy',
            'recruitmentInterviewer',
            'recruitmentMatchedUser.status',
            'recruitmentTierRatings.user',
        ]);
    }

    public function assignInterviewer(ContactSubmission $submission, User $interviewer): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        if (! $interviewer->can('recruitment-applications.manage')) {
            throw ValidationException::withMessages([
                'interviewer_user_id' => 'El entrevistador debe tener el permiso Gestión de alistados.',
            ]);
        }

        $submission->forceFill([
            'recruitment_interviewer_user_id' => $interviewer->id,
            'read_at' => $submission->read_at ?? now(),
        ])->save();

        return $submission->fresh(['recruitmentInterviewer', 'recruitmentTierRatings.user']);
    }

    public function assignMatchedUser(ContactSubmission $submission, ?User $user): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        $changes = [
            'recruitment_matched_user_id' => $user?->id,
        ];

        if ($user === null) {
            $changes['recruited_at'] = null;
        } elseif ($this->isRecruit($user)) {
            $changes['recruited_at'] = $submission->recruited_at ?? now();
        } else {
            $changes['recruited_at'] = null;
        }

        $submission->forceFill($changes)->save();

        return $submission->fresh([
            'recruitmentReviewedBy',
            'recruitmentInterviewer',
            'recruitmentMatchedUser.status',
            'recruitmentTierRatings.user',
        ]);
    }

    public function setTier(
        ContactSubmission $submission,
        int $tier,
        ?string $reason,
        ?int $markedByUserId,
    ): ContactSubmission {
        $this->assertRecruitmentSubmission($submission);

        if (! in_array($tier, [
            ContactSubmission::TIER_1,
            ContactSubmission::TIER_2,
            ContactSubmission::TIER_3,
        ], true)) {
            throw ValidationException::withMessages([
                'tier' => 'Selecciona TIER 1, TIER 2 o TIER 3.',
            ]);
        }

        if ($markedByUserId === null) {
            throw ValidationException::withMessages([
                'tier' => 'No se ha podido identificar al reclutador que pone la valoración.',
            ]);
        }

        $reason = trim((string) $reason);

        DB::transaction(function () use ($submission, $tier, $reason, $markedByUserId): void {
            RecruitmentApplicationTierRating::query()->updateOrCreate(
                [
                    'contact_submission_id' => $submission->id,
                    'user_id' => $markedByUserId,
                ],
                [
                    'tier' => $tier,
                    'reason' => $reason !== '' ? $reason : null,
                ],
            );

            $submission->forceFill([
                'recruitment_tier' => $tier,
                'recruitment_tier_reason' => $reason !== '' ? $reason : null,
                'recruitment_tier_marked_by' => $markedByUserId,
                'recruitment_tier_marked_at' => now(),
                'read_at' => $submission->read_at ?? now(),
            ])->save();
        });

        return $submission->fresh([
            'recruitmentTierMarkedBy',
            'recruitmentInterviewer',
            'recruitmentTierRatings.user',
        ]);
    }

    public function clearInterviewer(ContactSubmission $submission, User $interviewer): ContactSubmission
    {
        $this->assertRecruitmentSubmission($submission);

        if ((int) $submission->recruitment_interviewer_user_id === (int) $interviewer->id) {
            $submission->forceFill([
                'recruitment_interviewer_user_id' => null,
            ])->save();
        }

        return $submission->fresh(['recruitmentInterviewer', 'recruitmentTierRatings.user']);
    }

    /**
     * Enlaza solicitudes aprobadas cuando la cuenta se crea después de que el
     * reclutador haya tomado la decisión o cuando cambia el email del usuario.
     */
    public function syncUser(User $user): void
    {
        if (! $this->schemaReady() || blank($user->email)) {
            return;
        }

        $email = Str::lower(trim((string) $user->email));

        ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->where(function ($query) use ($user, $email): void {
                $query->where('recruitment_matched_user_id', $user->id)
                    ->orWhere(function ($query) use ($email): void {
                        $query->whereNull('recruitment_matched_user_id')
                            ->whereRaw('LOWER(email) = ?', [$email]);
                    });
            })
            ->each(function (ContactSubmission $submission) use ($user): void {
                $changes = [
                    'recruitment_matched_user_id' => $user->id,
                ];

                if ($this->isRecruit($user) && $submission->recruited_at === null) {
                    $changes['recruited_at'] = now();
                }

                $submission->forceFill($changes)->save();
            });
    }

    /**
     * Marca como reclutadas las solicitudes aprobadas cuando el usuario entra
     * realmente en estado RECLUTA. El dato queda histórico aunque luego pase a
     * ACTIVO, RESERVA u otro estado.
     */
    public function markRecruitmentStarted(User $user): void
    {
        if (! $this->schemaReady() || ! $this->isRecruit($user)) {
            return;
        }

        $email = Str::lower(trim((string) $user->email));

        ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->whereNull('recruited_at')
            ->where(function ($query) use ($user, $email): void {
                $query->where('recruitment_matched_user_id', $user->id);

                if ($email !== '') {
                    $query->orWhere(function ($query) use ($email): void {
                        $query->whereNull('recruitment_matched_user_id')
                            ->whereRaw('LOWER(email) = ?', [$email]);
                    });
                }
            })
            ->update([
                'recruitment_matched_user_id' => $user->id,
                'recruited_at' => now(),
                'updated_at' => now(),
            ]);
    }

    public function findMatchingUser(string $email): ?User
    {
        $email = Str::lower(trim($email));

        if ($email === '') {
            return null;
        }

        return User::query()
            ->with('status')
            ->whereRaw('LOWER(email) = ?', [$email])
            ->first();
    }

    private function isRecruit(User $user): bool
    {
        $user->loadMissing('status');

        return strtoupper(trim((string) $user->status?->name)) === 'RECLUTA';
    }

    private function schemaReady(): bool
    {
        return Schema::hasTable('contact_submissions')
            && Schema::hasColumn('contact_submissions', 'recruitment_review_status')
            && Schema::hasColumn('contact_submissions', 'recruitment_matched_user_id')
            && Schema::hasColumn('contact_submissions', 'recruited_at');
    }

    private function assertRecruitmentSubmission(ContactSubmission $submission): void
    {
        if (! $submission->is_recruitment) {
            throw new \InvalidArgumentException('La solicitud no es de alistamiento.');
        }
    }
}
