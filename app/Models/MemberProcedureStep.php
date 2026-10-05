<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberProcedureStep extends Model
{
    use Auditable;

    public const KIND_AUTOMATIC = 'automatic';
    public const KIND_MANUAL = 'manual';
    public const KIND_WAITING = 'waiting';

    public const STATUS_PENDING = 'pending';
    public const STATUS_MANUAL = 'manual_pending';
    public const STATUS_WAITING = 'waiting';
    public const STATUS_RUNNING = 'running';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR = 'error';
    public const STATUS_SKIPPED = 'skipped';

    protected $fillable = [
        'member_procedure_id', 'step_key', 'label', 'kind', 'status',
        'position', 'required', 'attempts', 'meta', 'result', 'last_error',
        'completed_by_user_id', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'required' => 'boolean',
            'attempts' => 'integer',
            'meta' => 'array',
            'result' => 'array',
            'completed_at' => 'datetime',
        ];
    }

    public function procedure()
    {
        return $this->belongsTo(MemberProcedure::class, 'member_procedure_id');
    }

    public function completedBy()
    {
        return $this->belongsTo(User::class, 'completed_by_user_id')->withTrashed();
    }

    public function isFinished(): bool
    {
        return in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_SKIPPED], true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_PENDING => 'Pendiente',
            self::STATUS_MANUAL => 'Pendiente manual',
            self::STATUS_WAITING => 'En espera',
            self::STATUS_RUNNING => 'Ejecutando',
            self::STATUS_COMPLETED => 'Completado',
            self::STATUS_ERROR => 'Error',
            self::STATUS_SKIPPED => 'Omitido',
            default => $this->status,
        };
    }
}
