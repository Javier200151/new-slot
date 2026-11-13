<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommunityRaffleDraw extends Model
{
    use Auditable;

    protected $fillable = [
        'raffle_id', 'prize_id', 'winner_user_id', 'winner_nick', 'prize_name',
        'phrase', 'status', 'winner_index', 'candidate_count', 'wheel_snapshot', 'started_at',
        'ends_at', 'completed_at', 'voided_at', 'created_by',
    ];

    protected function casts(): array
    {
        return [
            'started_at' => 'datetime', 'ends_at' => 'datetime',
            'completed_at' => 'datetime', 'voided_at' => 'datetime',
            'winner_index' => 'integer', 'candidate_count' => 'integer', 'wheel_snapshot' => 'array',
        ];
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(CommunityRaffle::class, 'raffle_id');
    }

    public function prize(): BelongsTo
    {
        return $this->belongsTo(CommunityRafflePrize::class, 'prize_id');
    }

    public function winner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'winner_user_id')->withTrashed();
    }
}
