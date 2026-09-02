<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityPollVote extends Model
{
    use Auditable;

    protected $fillable = [
        'community_poll_id',
        'community_poll_option_id',
        'user_id',
        'is_abstain',
    ];

    protected function casts(): array
    {
        return [
            'is_abstain' => 'boolean',
        ];
    }

    public function poll()
    {
        return $this->belongsTo(CommunityPoll::class, 'community_poll_id');
    }

    public function option()
    {
        return $this->belongsTo(CommunityPollOption::class, 'community_poll_option_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }
}
