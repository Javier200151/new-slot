<?php

namespace App\Models;

use App\Models\Concerns\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class CommunityClip extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'user_id', 'streamer_id', 'platform', 'external_id', 'source', 'title',
        'url', 'thumbnail_url', 'broadcaster_name', 'game_id', 'duration_seconds',
        'is_visible', 'clip_created_at', 'event_id', 'protagonist_user_id',
    ];

    protected function casts(): array
    {
        return [
            'is_visible' => 'boolean',
            'clip_created_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function event()
    {
        return $this->belongsTo(Event::class);
    }

    public function protagonist()
    {
        return $this->belongsTo(User::class, 'protagonist_user_id');
    }

    public function streamer()
    {
        return $this->belongsTo(Streamer::class);
    }

    public function likes()
    {
        return $this->hasMany(CommunityClipLike::class, 'community_clip_id');
    }

    public function comments()
    {
        return $this->hasMany(CommunityClipComment::class, 'community_clip_id');
    }

    public function scopeVisible($query)
    {
        return $query->where('is_visible', true);
    }

    public function scopeRecent($query)
    {
        return $query->where('clip_created_at', '>=', now()->subDays(7));
    }

    public function embedUrl(): string
    {
        if ($this->platform === 'youtube') {
            return 'https://www.youtube-nocookie.com/embed/' . rawurlencode($this->external_id) . '?playsinline=1&rel=0';
        }

        return 'https://clips.twitch.tv/embed?clip=' . rawurlencode($this->external_id)
            . '&parent=' . rawurlencode((string) request()->getHost()) . '&autoplay=false';
    }
}
