<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class CommunitySubscription extends Model
{
    protected $fillable = [
        'user_id',
        'subscribable_type',
        'subscribable_id',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function subscribable(): MorphTo
    {
        return $this->morphTo();
    }
}
