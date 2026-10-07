<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberProcedure extends Model
{
    use Auditable;

    public const TYPE_RECRUITMENT_START = 'recruitment_start';
    public const TYPE_RECRUITMENT_COMPLETE = 'recruitment_complete';
    public const TYPE_NOT_PROMOTED = 'not_promoted';
    public const TYPE_REACTIVATION = 'reactivation';
    public const TYPE_RESERVE = 'reserve';
    public const TYPE_DEPARTURE = 'departure';
    public const TYPE_DISMISSAL = 'dismissal';

    public const STATUS_IN_PROGRESS = 'in_progress';
    public const STATUS_ERROR = 'error';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_CANCELLED = 'cancelled';

    protected $fillable = [
        'user_id', 'type', 'status', 'input', 'started_by_user_id',
        'started_at', 'completed_at', 'cancelled_at', 'cancel_reason',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public static function typeLabels(): array
    {
        return [
            self::TYPE_RECRUITMENT_START => 'Inicio de reclutamiento',
            self::TYPE_RECRUITMENT_COMPLETE => 'Alta de calavera (ACTIVO)',
            self::TYPE_NOT_PROMOTED => 'No promocionado',
            self::TYPE_REACTIVATION => 'Reactivación desde reserva',
            self::TYPE_RESERVE => 'Paso a reserva',
            self::TYPE_DEPARTURE => 'Baja',
            self::TYPE_DISMISSAL => 'Cese',
        ];
    }

    public function typeLabel(): string
    {
        return self::typeLabels()[$this->type] ?? $this->type;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_IN_PROGRESS => 'En curso',
            self::STATUS_ERROR => 'Con errores',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_CANCELLED => 'Cancelado',
            default => $this->status,
        };
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function startedBy()
    {
        return $this->belongsTo(User::class, 'started_by_user_id')->withTrashed();
    }

    public function steps()
    {
        return $this->hasMany(MemberProcedureStep::class)->orderBy('position')->orderBy('id');
    }

    public function notifications()
    {
        return $this->hasMany(ProcedureNotification::class);
    }
}
