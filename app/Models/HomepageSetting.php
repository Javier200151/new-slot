<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class HomepageSetting extends Model
{
    protected $fillable = [
        'recruitment_open',
        'contact_email',
        'instagram_url',
        'google_photos_url',
        'news_title',
        'news_intro',
        'streams_title',
        'streams_intro',
    ];

    protected function casts(): array
    {
        return ['recruitment_open' => 'boolean'];
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
