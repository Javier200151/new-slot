<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RecruitmentPeriod extends Model
{
    use Auditable;

    protected static function booted(): void
    {
        static::updating(function (RecruitmentPeriod $period): void {
            $wasOpen = $period->getOriginal('ended_at') === null
                && $period->getOriginal('open_user_id') !== null;

            if (! $wasOpen) {
                throw new \LogicException('Los periodos de reclutamiento cerrados son históricos y no pueden modificarse.');
            }

            if ($period->isDirty('started_at')) {
                $period->started_at_source = 'manual';
            }
        });

        static::saved(function (RecruitmentPeriod $period): void {
            if (! $period->isOpen()) {
                return;
            }

            // Compatibilidad temporal con users.tutor_id. El dato canónico
            // permanece en el periodo y este espejo podrá retirarse más adelante.
            \Illuminate\Support\Facades\DB::table('users')
                ->where('id', $period->user_id)
                ->update([
                    'tutor_id' => $period->tutor_id,
                    'updated_at' => now(),
                ]);
        });
    }

    public const PROCESS_PENDING_TUTOR = 'PENDING_TUTOR';
    public const PROCESS_IN_PROGRESS = 'IN_PROGRESS';
    public const PROCESS_PENDING_PROMOTION = 'PENDING_PROMOTION';
    public const PROCESS_CLOSED = 'CLOSED';

    public const RESULT_PROMOTED = 'PROMOTED';
    public const RESULT_NOT_PROMOTED = 'NOT_PROMOTED';

    public const TUTORIALS_NO = 'NO';
    public const TUTORIALS_PARTIAL = 'PARTIAL';
    public const TUTORIALS_YES = 'YES';

    public const DIARY_VERY_GOOD = 'VERY_GOOD';
    public const DIARY_GOOD = 'GOOD';
    public const DIARY_IMPROVABLE = 'IMPROVABLE';
    public const DIARY_DEFICIENT = 'DEFICIENT';

    protected $fillable = [
        'user_id',
        'open_user_id',
        'period_number',
        'tutor_id',
        'process_status',
        'result',
        'tutorials_status',
        'diary_rating',
        'official_events_allowed',
        'current_note',
        'promotion_pending_at',
        'promotion_pending_by',
        'started_at',
        'started_at_source',
        'ended_at',
        'final_status_id',
        'final_status_name',
        'events_played_final',
        'user_nick_snapshot',
        'tutor_nick_snapshot',
        'closed_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'official_events_allowed' => 'boolean',
            'promotion_pending_at' => 'datetime',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function openUser()
    {
        return $this->belongsTo(User::class, 'open_user_id')->withTrashed();
    }

    public function tutor()
    {
        return $this->belongsTo(User::class, 'tutor_id')->withTrashed();
    }

    public function finalStatus()
    {
        return $this->belongsTo(Status::class, 'final_status_id')->withTrashed();
    }

    public function promotionPendingBy()
    {
        return $this->belongsTo(User::class, 'promotion_pending_by')->withTrashed();
    }

    public function closedBy()
    {
        return $this->belongsTo(User::class, 'closed_by_user_id')->withTrashed();
    }

    public function reinforcementAreas()
    {
        return $this->belongsToMany(
            RecruitmentReinforcementArea::class,
            'recruitment_period_reinforcement_area'
        );
    }

    public function comments()
    {
        return $this->hasMany(RecruitmentPeriodComment::class)
            ->oldest('created_at')
            ->oldest('id');
    }

    public function isOpen(): bool
    {
        return $this->ended_at === null && $this->open_user_id !== null;
    }

    public function canEditStartedAt(): bool
    {
        return $this->isOpen();
    }
}
