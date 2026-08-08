<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EventSlot extends Model
{
    protected $fillable = [
        'event_id',
        'slot_key',
        'user_id',
        'ally_id',
        'name',
        'slot_type_id',
        'slot_group',
        'faction_id',
        'created_by',
        'updated_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventSlot $eventSlot): void {
            if (Auth::check()) {
                $eventSlot->created_by = Auth::id();
                $eventSlot->updated_by = Auth::id();
            }
        });

        static::updating(function (EventSlot $eventSlot): void {
            if (Auth::check()) {
                $eventSlot->updated_by = Auth::id();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function ally()
    {
        return $this->belongsTo(Ally::class);
    }

    public function slotType()
    {
        return $this->belongsTo(SlotType::class);
    }

    public function faction()
    {
        return $this->belongsTo(Faction::class);
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
