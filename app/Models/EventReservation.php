<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EventReservation extends Model
{
    use Auditable;

    protected $fillable = [
        'event_id',
        'user_id',
        'created_by',
    ];

    protected static function booted(): void
    {
        static::creating(function (EventReservation $reservation): void {
            if (Auth::check() && blank($reservation->created_by)) {
                $reservation->created_by = Auth::id();
            }
        });
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }
}
