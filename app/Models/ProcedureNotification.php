<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class ProcedureNotification extends Model
{
    use Auditable;

    public const TARGET_GROUP = 'sqa_group';
    public const TARGET_USER = 'user';

    protected $fillable = [
        'member_procedure_id', 'member_procedure_step_id', 'target_type', 'target_id',
        'title', 'body', 'dedupe_key', 'acknowledged_at', 'acknowledged_by_user_id',
    ];

    protected function casts(): array
    {
        return ['acknowledged_at' => 'datetime'];
    }

    public function procedure()
    {
        return $this->belongsTo(MemberProcedure::class, 'member_procedure_id');
    }

    public function step()
    {
        return $this->belongsTo(MemberProcedureStep::class, 'member_procedure_step_id');
    }

    public function acknowledgedBy()
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id')->withTrashed();
    }
}
