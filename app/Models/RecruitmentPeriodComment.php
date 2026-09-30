<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class RecruitmentPeriodComment extends Model
{
    use Auditable;

    protected $fillable = [
        'recruitment_period_id',
        'user_id',
        'content',
    ];

    public function period()
    {
        return $this->belongsTo(RecruitmentPeriod::class, 'recruitment_period_id');
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }
}
