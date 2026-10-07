<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    protected $fillable = [
        'recruitment_open',
        'contact_email',
        'instagram_url',
        'x_url',
        'youtube_url',
        'discord_invite_url',
        'discord_invite_auto_refresh',
        'discord_invite_refreshed_at',
        'discord_invite_expires_at',
        'google_photos_url',
        'news_title',
        'news_intro',
        'streams_title',
        'streams_intro',
    ];

    protected function casts(): array
    {
        return [
            'recruitment_open' => 'boolean',
            'discord_invite_auto_refresh' => 'boolean',
            'discord_invite_refreshed_at' => 'datetime',
            'discord_invite_expires_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return static::query()->firstOrCreate([], [
            'recruitment_open' => false,
            'contact_email' => config('mail.from.address'),
            'news_title' => 'Actualidad de Squad ALPHA',
            'streams_title' => 'Últimos VODs de la comunidad',
        ]);
    }
};
