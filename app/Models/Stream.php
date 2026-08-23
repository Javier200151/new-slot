<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    use Auditable;

    protected $fillable = [
        'event_id',
        'streamer_id',
        'platform',
        'stream_url',
        'enabled',
        'title',
        'started_at',
        'ended_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function streamer()
    {
        return $this->belongsTo(Streamer::class);
    }
}