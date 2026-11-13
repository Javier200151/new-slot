<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityRaffleEntry extends Model
{
    protected $fillable = ['raffle_id', 'user_id'];

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(CommunityRaffle::class, 'raffle_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
