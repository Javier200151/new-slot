<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class VeterancyAward extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'level',
        'metopa_id',
        'effective_days',
        'earned_at',
        'approved_at',
        'approved_by_user_id',
        'community_post_id',
    ];

    protected function casts(): array
    {
        return [
            'earned_at' => 'datetime',
            'approved_at' => 'datetime',
            'effective_days' => 'integer',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function metopa()
    {
        return $this->belongsTo(Metopa::class)->withTrashed();
    }

    public function approvedBy()
    {
        return $this->belongsTo(User::class, 'approved_by_user_id')->withTrashed();
    }

    public function communityPost()
    {
        return $this->belongsTo(CommunityPost::class);
    }
}
