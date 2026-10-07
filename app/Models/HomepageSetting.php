<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

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
        'discord_account_logo',
        'steam_account_logo',
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
        $defaults = [
            'recruitment_open' => false,
            'contact_email' => config('mail.from.address'),
            'news_title' => 'Actualidad de Squad ALPHA',
            'streams_title' => 'Últimos VODs de la comunidad',
        ];

        // Algunas pruebas construyen esquemas SQLite mínimos para aislar la
        // funcionalidad que están verificando. El footer se reutiliza en esas
        // vistas y no debe convertir homepage_settings en una dependencia
        // obligatoria de tests ajenos a la portada.
        if (app()->environment('testing') && ! Schema::hasTable((new static())->getTable())) {
            return new static($defaults);
        }

        return static::query()->firstOrCreate([], $defaults);
    }
};
