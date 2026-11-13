<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Validation\ValidationException;

class CommunityRafflePrize extends Model
{
    use Auditable;

    protected $fillable = ['raffle_id', 'name', 'description', 'image_path', 'quantity', 'sort_order'];

    protected function casts(): array
    {
        return ['quantity' => 'integer', 'sort_order' => 'integer'];
    }

    protected static function booted(): void
    {
        static::deleting(function (self $prize): void {
            if (CommunityRaffleDraw::where('prize_id', $prize->id)->exists()) {
                throw ValidationException::withMessages(['premio' => 'No puedes eliminar un premio que ya ha sido sorteado.']);
            }
        });
    }

    public function raffle(): BelongsTo
    {
        return $this->belongsTo(CommunityRaffle::class, 'raffle_id');
    }
}
