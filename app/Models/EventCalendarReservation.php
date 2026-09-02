<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;

class EventCalendarReservation extends Model
{
    use Auditable;

    protected $fillable = [
        'reserved_date',
        'user_id',
        'reserved_for_nick',
        'comment',
        'created_by',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'reserved_date' => 'date',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (EventCalendarReservation $reservation): void {
            if (Auth::check()) {
                $reservation->created_by ??= Auth::id();
                $reservation->updated_by ??= Auth::id();
            }
        });

        static::updating(function (EventCalendarReservation $reservation): void {
            if (Auth::check()) {
                $reservation->updated_by = Auth::id();
            }
        });
    }

    public function user()
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    public function createdBy()
    {
        return $this->belongsTo(User::class, 'created_by')->withTrashed();
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by')->withTrashed();
    }
}
