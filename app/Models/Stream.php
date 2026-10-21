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
        'is_vod',
        'visible_on_home',
        'auto_detected',
        'twitch_stream_id',
        'twitch_video_id',
        'thumbnail_url',
        'published_at',
        'twitch_last_seen_at',
    ];

    protected function casts(): array
    {
        return [
            'enabled' => 'boolean',
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
            'is_vod' => 'boolean',
            'visible_on_home' => 'boolean',
            'auto_detected' => 'boolean',
            'published_at' => 'datetime',
            'twitch_last_seen_at' => 'datetime',
        ];
    }

    public function isVodForHome(): bool
    {
        if ($this->is_vod) {
            return true;
        }
        if ($this->platform === 'youtube' && $this->ended_at !== null) {
            return true;
        }
        return $this->platform === 'twitch'
            && (bool) preg_match('~twitch\.tv/videos/\d+~i', (string) $this->stream_url);
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