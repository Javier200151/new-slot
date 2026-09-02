<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class CommunityDiary extends Model
{
    use Auditable;

    protected $fillable = [
        'user_id',
        'author_nick',
    ];

    public function author()
    {
        return $this->belongsTo(User::class, 'user_id')->withTrashed();
    }

    public function entries()
    {
        return $this->hasMany(CommunityDiaryEntry::class)->latest('created_at');
    }

    public function comments()
    {
        return $this->hasMany(CommunityDiaryComment::class)->oldest('created_at');
    }

    public function subscriptions()
    {
        return $this->morphMany(CommunitySubscription::class, 'subscribable');
    }
}
