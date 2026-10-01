<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RecruitmentApplicationTierRating extends Model
{
    protected $fillable = [
        'contact_submission_id',
        'user_id',
        'tier',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'tier' => 'integer',
        ];
    }

    public function submission()
    {
        return $this->belongsTo(ContactSubmission::class, 'contact_submission_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function tierLabel(): string
    {
        return match ((int) $this->tier) {
            ContactSubmission::TIER_1 => 'TIER 1',
            ContactSubmission::TIER_2 => 'TIER 2',
            ContactSubmission::TIER_3 => 'TIER 3',
            default => 'Sin TIER',
        };
    }

    public function tierColor(): string
    {
        return match ((int) $this->tier) {
            ContactSubmission::TIER_1 => 'success',
            ContactSubmission::TIER_2 => 'warning',
            ContactSubmission::TIER_3 => 'danger',
            default => 'gray',
        };
    }
}
