<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Validation\ValidationException;

class CommunityRaffle extends Model
{
    use Auditable;

    protected $fillable = [
        'title', 'description', 'claim_message', 'winner_messages',
        'status', 'show_in_sidebar', 'active_key', 'created_by', 'opened_at', 'closed_at',
        'participants_updated_at', 'participants_updated_by',
    ];

    protected function casts(): array
    {
        return [
            'winner_messages' => 'array',
            'show_in_sidebar' => 'boolean',
            'opened_at' => 'datetime',
            'closed_at' => 'datetime',
            'participants_updated_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $raffle): void {
            if ($raffle->status !== 'draft' || $raffle->draws()->exists()) {
                throw ValidationException::withMessages(['raffle' => 'Un sorteo abierto o con resultados no puede eliminarse.']);
            }
        });
    }

    public function prizes(): HasMany
    {
        return $this->hasMany(CommunityRafflePrize::class, 'raffle_id')->orderBy('sort_order')->orderBy('id');
    }

    public function entries(): HasMany
    {
        return $this->hasMany(CommunityRaffleEntry::class, 'raffle_id');
    }

    public function draws(): HasMany
    {
        return $this->hasMany(CommunityRaffleDraw::class, 'raffle_id');
    }
}
