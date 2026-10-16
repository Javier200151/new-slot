<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\Auditable;

class EventSlot extends Model
{
    use Auditable;

    public bool $retainExplicitStatusColor = false;
    protected $fillable = [
        'event_id',
        'slot_key',
        'user_id',
        'status_color_snapshot',
        'ally_id',
        'ally_nickname',
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
            $eventSlot->syncOccupantColor();
            if (Auth::check()) {
                $eventSlot->created_by = Auth::id();
                $eventSlot->updated_by = Auth::id();
            }
        });

        static::updating(function (EventSlot $eventSlot): void {
            if ($eventSlot->isDirty('user_id') && ! $eventSlot->retainExplicitStatusColor) {
                $eventSlot->syncOccupantColor();
            }
            if (Auth::check()) {
                $eventSlot->updated_by = Auth::id();
            }
        });
    }

    /** El color pertenece a la inscripción, no al estado actual del usuario. */
    public function syncOccupantColor(): void
    {
        if (! $this->user_id) {
            $this->status_color_snapshot = null;
            return;
        }

        $this->status_color_snapshot = User::query()
            ->withTrashed()
            ->find($this->user_id)?->getStatusColor();
    }

    public function occupantStatusColor(): ?string
    {
        return $this->status_color_snapshot ?: $this->user?->getStatusColor();
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

    public function history()
    {
        return $this->hasMany(EventSlotHistory::class);
    }
}
