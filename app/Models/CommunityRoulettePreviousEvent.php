<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;

class CommunityRoulettePreviousEvent extends Model
{
    use Auditable;

    protected $fillable = [
        'room_id',
        'event_id',
        'position',
        'event_name_snapshot',
        'event_date_snapshot',
    ];

    protected function casts(): array
    {
        return [
            'position' => 'integer',
            'event_date_snapshot' => 'datetime',
        ];
    }

    public function room()
    {
        return $this->belongsTo(CommunityRouletteRoom::class, 'room_id');
    }

    public function event()
    {
        return $this->belongsTo(Event::class)->withTrashed();
    }
}
