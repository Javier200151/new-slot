<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CommunityRouletteCandidate extends Model
{
    protected $fillable = [
        'room_id',
        'user_id',
        'nick_snapshot',
        'status_snapshot',
        'member_at_snapshot',
        'current_slot_key',
        'current_slot_name',
        'current_slot_type_id',
        'base_tickets',
        'tickets',
        'manual_ticket_adjustment',
        'is_exceptional',
        'previous_responsibility_count',
        'excluded_reason',
        'details',
        'is_winner',
    ];

    protected function casts(): array
    {
        return [
            'member_at_snapshot' => 'date',
            'base_tickets' => 'integer',
            'tickets' => 'integer',
            'manual_ticket_adjustment' => 'integer',
            'is_exceptional' => 'boolean',
            'previous_responsibility_count' => 'integer',
            'details' => 'array',
            'is_winner' => 'boolean',
        ];
    }

    public function room()
    {
        return $this->belongsTo(CommunityRouletteRoom::class, 'room_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function currentSlotType()
    {
        return $this->belongsTo(SlotType::class, 'current_slot_type_id');
    }
}
