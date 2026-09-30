<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RecruitmentReentryReview extends Model
{
    use Auditable;

    public const TYPE_PROMOTED_TO_RECRUIT = 'PROMOTED_TO_RECRUIT';
    public const TYPE_RESERVE_TO_ACTIVE = 'RESERVE_TO_ACTIVE';

    public const RESOLUTION_NEW_PERIOD_STARTED = 'NEW_PERIOD_STARTED';
    public const RESOLUTION_STATUS_CHANGE_ERROR = 'STATUS_CHANGE_ERROR';
    public const RESOLUTION_TUTORING_APPROVED = 'TUTORING_APPROVED';
    public const RESOLUTION_NO_TUTORING_REQUIRED = 'NO_TUTORING_REQUIRED';

    protected $fillable = [
        'user_id',
        'pending_user_id',
        'previous_period_id',
        'review_type',
        'tutorial_tutor_user_id',
        'tutorial_approved_at',
        'detected_at',
        'resolved_at',
        'resolution',
        'resolved_by_user_id',
        'resolution_note',
    ];

    protected function casts(): array
    {
        return [
            'detected_at' => 'datetime',
            'resolved_at' => 'datetime',
            'tutorial_approved_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function pendingUser()
    {
        return $this->belongsTo(User::class, 'pending_user_id')->withTrashed();
    }

    public function previousPeriod()
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'previous_period_id');
    }

    public function tutorialTutor()
    {
        return $this->belongsTo(User::class, 'tutorial_tutor_user_id')->withTrashed();
    }

    public function resolvedBy()
    {
        return $this->belongsTo(User::class, 'resolved_by_user_id')->withTrashed();
    }

    public function isPending(): bool
    {
        return $this->resolved_at === null && $this->pending_user_id !== null;
    }

    public function isReserveReturn(): bool
    {
        return $this->review_type === self::TYPE_RESERVE_TO_ACTIVE;
    }
}
