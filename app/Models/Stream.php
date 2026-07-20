<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Stream extends Model
{
    protected $fillable = [
        'event_id',
        'streamer_id',
        'stream_url',
    ];

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function streamer()
    {
        return $this->belongsTo(Streamer::class);
    }
}
