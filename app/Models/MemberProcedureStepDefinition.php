<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class MemberProcedureStepDefinition extends Model
{
    use Auditable;

    protected $fillable = [
        'member_procedure_setting_id',
        'procedure_type',
        'step_key',
        'label',
        'instructions',
        'kind',
        'position',
        'required',
        'is_enabled',
        'is_system',
        'depends_on',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'required' => 'boolean',
            'is_enabled' => 'boolean',
            'is_system' => 'boolean',
            'depends_on' => 'array',
        ];
    }

    public function setting()
    {
        return $this->belongsTo(MemberProcedureSetting::class, 'member_procedure_setting_id');
    }

    public function procedureLabel(): string
    {
        return MemberProcedure::typeLabels()[$this->procedure_type] ?? $this->procedure_type;
    }

    public function kindLabel(): string
    {
        return match ($this->kind) {
            MemberProcedureStep::KIND_AUTOMATIC => 'Automático',
            MemberProcedureStep::KIND_MANUAL => 'Manual',
            MemberProcedureStep::KIND_WAITING => 'Espera automática',
            default => $this->kind,
        };
    }

    public function canBeDeleted(): bool
    {
        return $this->kind === MemberProcedureStep::KIND_MANUAL;
    }
}
