<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RecruitmentReinforcementArea extends Model
{
    use Auditable;

    protected $fillable = [
        'name',
        'description',
        'active',
    ];

    protected function casts(): array
    {
        return ['active' => 'boolean'];
    }

    public function recruitmentPeriods()
    {
        return $this->belongsToMany(
            RecruitmentPeriod::class,
            'recruitment_period_reinforcement_area'
        );
    }

    public function deletionBlockReason(): ?string
    {
        if ($this->recruitmentPeriods()->exists()) {
            return 'No puede eliminarse porque ya forma parte del histórico de uno o más periodos. Desactívala en su lugar.';
        }

        return null;
    }
}
