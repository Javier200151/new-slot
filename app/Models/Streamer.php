<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Models\Concerns\Auditable;

class Streamer extends Model
{
    use SoftDeletes, Auditable;

    protected $fillable = [
        'user_id',
        'enable',
        'auto_import_clips',
        'auto_import_youtube_clips',
        'clip_import_window',
        'clip_import_mode',

        'twitch_channel',
        'twitch_user_id',

        'youtube_channel',
        'youtube_channel_id',

        'other_channel',
        'website_url',
    ];

    protected function casts(): array
    {
        return [
            'enable' => 'boolean',
            'auto_import_clips' => 'boolean',
            'auto_import_youtube_clips' => 'boolean',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function clips()
    {
        return $this->hasMany(CommunityClip::class);
    }

    public function streams()
    {
        return $this->hasMany(Stream::class);
    }
}
