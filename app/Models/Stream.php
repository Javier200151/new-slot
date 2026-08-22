<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;

class Stream extends Model
{
    use Auditable;
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
