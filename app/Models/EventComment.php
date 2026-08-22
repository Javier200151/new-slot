<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\Auth;
use App\Models\Concerns\Auditable;

class EventComment extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'event_id',
        'user_id',
        'parent_id',
        'comment',
        'is_pinned',
        'updated_by',
    ];

    protected function casts(): array
    {
        return [
            'is_pinned' => 'boolean',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function ($eventComment): void {
            if (Auth::check()) {
                $eventComment->user_id ??= Auth::id();
                $eventComment->updated_by = Auth::id();
            }
        });

        static::updating(function ($eventComment): void {
            if (Auth::check()) {
                $eventComment->updated_by = Auth::id();
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

    public function parent()
    {
        return $this->belongsTo(EventComment::class, 'parent_id');
    }

    public function replies()
    {
        return $this->hasMany(EventComment::class, 'parent_id');
    }

    public function updatedBy()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }
}
