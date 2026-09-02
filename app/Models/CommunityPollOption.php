<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityPollOption extends Model
{
    use Auditable;

    protected $fillable = [
        'community_poll_id',
        'candidate_user_id',
        'label',
        'sort_order',
    ];

    public function candidate()
    {
        return $this->belongsTo(User::class, 'candidate_user_id')->withTrashed();
    }

    public function poll()
    {
        return $this->belongsTo(CommunityPoll::class, 'community_poll_id');
    }

    public function votes()
    {
        return $this->hasMany(CommunityPollVote::class);
    }
}
