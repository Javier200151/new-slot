<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class EventSlotHistory extends Model
{
    use Auditable;
    protected $table = 'event_slot_history';

    public $timestamps = false;

    protected $fillable = [
        'event_slot_id',
        'event_id',
        'ally_id',
        'user_id',
        'action',
        'from_slot_key',
        'from_slot_name',
        'from_slot_type_id',
        'from_slot_group',
        'from_army_id',
        'to_slot_key',
        'to_slot_name',
        'to_slot_type_id',
        'to_slot_group',
        'to_army_id',
        'changed_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
        ];
    }

    public function eventSlot()
    {
        return $this->belongsTo(EventSlot::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function ally()
    {
        return $this->belongsTo(Ally::class);
    }

    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id')->withTrashed();
    }

    public function fromSlotType()
    {
        return $this->belongsTo(SlotType::class, 'from_slot_type_id');
    }

    public function toSlotType()
    {
        return $this->belongsTo(SlotType::class, 'to_slot_type_id');
    }

    public function fromArmy()
    {
        return $this->belongsTo(Army::class, 'from_army_id');
    }

    public function toArmy()
    {
        return $this->belongsTo(Army::class, 'to_army_id');
    }
}
