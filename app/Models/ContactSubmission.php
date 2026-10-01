<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

class ContactSubmission extends Model
{
    public const REVIEW_UNREVIEWED = 'unreviewed';
    public const REVIEW_DISCARDED = 'discarded';
    public const REVIEW_APPROVED = 'approved';

    public const TIER_1 = 1;
    public const TIER_2 = 2;
    public const TIER_3 = 3;

    protected $fillable = [
        'nickname', 'email', 'message', 'is_recruitment',
        'full_name', 'birth_date', 'residence', 'phone_whatsapp', 'discord_profile', 'how_heard_us',
        'accepted_rules', 'is_adult', 'accepts_contributions', 'has_required_game_content',
        'tuesday_available', 'friday_available', 'has_previous_experience', 'experience_summary',
        'accepted_privacy', 'accepted_contact', 'ip_address', 'user_agent', 'read_at',
        'recruitment_review_status', 'recruitment_reviewed_at', 'recruitment_reviewed_by',
        'recruitment_interviewer_user_id', 'recruitment_matched_user_id', 'recruited_at',
        'recruitment_tier', 'recruitment_tier_reason', 'recruitment_tier_marked_by', 'recruitment_tier_marked_at',
    ];

    public function recruitmentReviewedBy()
    {
        return $this->belongsTo(User::class, 'recruitment_reviewed_by')->withTrashed();
    }

    public function recruitmentInterviewer()
    {
        return $this->belongsTo(User::class, 'recruitment_interviewer_user_id')->withTrashed();
    }

    public function recruitmentMatchedUser()
    {
        return $this->belongsTo(User::class, 'recruitment_matched_user_id')->withTrashed();
    }

    public function recruitmentTierMarkedBy()
    {
        return $this->belongsTo(User::class, 'recruitment_tier_marked_by')->withTrashed();
    }


    public function recruitmentTierRatings()
    {
        return $this->hasMany(RecruitmentApplicationTierRating::class, 'contact_submission_id')
            ->with('user')
            ->orderBy('created_at');
    }

    public function recruitmentComments()
    {
        return $this->hasMany(RecruitmentApplicationComment::class, 'contact_submission_id')
            ->latest('created_at');
    }

    public function recruitmentWorkflowLabel(): string
    {
        return match ($this->recruitment_review_status) {
            self::REVIEW_DISCARDED => 'Descartada',
            self::REVIEW_APPROVED => $this->recruited_at
                ? 'Reclutado'
                : ($this->recruitment_matched_user_id
                    ? 'Pendiente de acceder al reclutamiento'
                    : 'Aprobada · sin usuario coincidente'),
            default => 'No valorada',
        };
    }

    public function recruitmentWorkflowColor(): string
    {
        return match ($this->recruitment_review_status) {
            self::REVIEW_DISCARDED => 'danger',
            self::REVIEW_APPROVED => $this->recruited_at ? 'success' : 'warning',
            default => 'gray',
        };
    }

    public function recruitmentTierLabel(): string
    {
        return match ((int) $this->recruitment_tier) {
            self::TIER_1 => 'TIER 1',
            self::TIER_2 => 'TIER 2',
            self::TIER_3 => 'TIER 3',
            default => 'Sin TIER',
        };
    }

    public function recruitmentTierColor(): string
    {
        return match ((int) $this->recruitment_tier) {
            self::TIER_1 => 'success',
            self::TIER_2 => 'warning',
            self::TIER_3 => 'danger',
            default => 'gray',
        };
    }

    public static function recruitmentTierOptions(): array
    {
        return [
            self::TIER_1 => 'TIER 1 · Mejor valoración',
            self::TIER_2 => 'TIER 2 · Valoración intermedia',
            self::TIER_3 => 'TIER 3 · Peor valoración',
        ];
    }


    public function currentUserRecruitmentTierRating(?int $userId): ?RecruitmentApplicationTierRating
    {
        if ($userId === null) {
            return null;
        }

        if ($this->relationLoaded('recruitmentTierRatings')) {
            return $this->recruitmentTierRatings
                ->first(fn (RecruitmentApplicationTierRating $rating): bool => (int) $rating->user_id === (int) $userId);
        }

        return $this->recruitmentTierRatings()
            ->where('user_id', $userId)
            ->first();
    }

    public function recruitmentTierRatingsSummary(): Collection
    {
        return $this->recruitmentTierRatings
            ->map(function (RecruitmentApplicationTierRating $rating): array {
                $nick = $rating->user?->nick ?? 'Usuario';

                return [
                    'id' => $rating->id,
                    'nick' => $nick,
                    'tier' => $rating->tierLabel(),
                    'color' => $rating->tierColor(),
                    'reason' => $rating->reason,
                    'label' => $nick . ' · ' . $rating->tierLabel(),
                ];
            });
    }

    public function tierColorHex(): string
    {
        return match ((int) $this->recruitment_tier) {
            self::TIER_1 => '#16a34a',
            self::TIER_2 => '#eab308',
            self::TIER_3 => '#f97316',
            default => '#6b7280',
        };
    }

    protected function casts(): array
    {
        return [
            'is_recruitment' => 'boolean',
            'birth_date' => 'date',
            'accepted_rules' => 'boolean',
            'is_adult' => 'boolean',
            'accepts_contributions' => 'boolean',
            'has_required_game_content' => 'boolean',
            'tuesday_available' => 'boolean',
            'friday_available' => 'boolean',
            'has_previous_experience' => 'boolean',
            'accepted_privacy' => 'boolean',
            'accepted_contact' => 'boolean',
            'read_at' => 'datetime',
            'recruitment_reviewed_at' => 'datetime',
            'recruited_at' => 'datetime',
            'recruitment_tier' => 'integer',
            'recruitment_tier_marked_at' => 'datetime',
        ];
    }
};
