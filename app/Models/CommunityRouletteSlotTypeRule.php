<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityRouletteSlotTypeRule extends Model
{
    protected $fillable = [
        'room_id',
        'slot_type_id',
        'slot_type_name_snapshot',
        'is_responsibility',
        'is_hq',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'is_responsibility' => 'boolean',
            'is_hq' => 'boolean',
        ];
    }

    public function room()
    {
        return $this->belongsTo(CommunityRouletteRoom::class, 'room_id');
    }

    public function slotType()
    {
        return $this->belongsTo(SlotType::class);
    }
}
