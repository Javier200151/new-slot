<?php

namespace App\Models;

use App\Models\Concerns\Auditable;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Schema;

class HomepageSetting extends Model
{
    use Auditable;

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
        'telegram_account_logo',
        'google_photos_url',
        'news_title',
        'news_intro',
        'news_home_limit',
        'news_visible_status_ids',
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
            'news_home_limit' => 'integer',
            'news_visible_status_ids' => 'array',
        ];
    }

    /** Solo los estados seleccionados pueden consultar el tablón y su historial. */
    public function canViewNews(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        $statuses = array_map('intval', (array) ($this->news_visible_status_ids ?? []));

        return in_array((int) $user->status_id, $statuses, true);
    }

    public function newsHomeLimit(): int
    {
        return max(1, min(6, (int) ($this->news_home_limit ?? 6)));
    }

    public static function current(): self
    {
        $defaults = [
            'recruitment_open' => false,
            'contact_email' => config('mail.from.address'),
            'news_title' => 'Actualidad de Squad ALPHA',
            'news_home_limit' => 6,
            'news_visible_status_ids' => Schema::hasTable('status')
                ? Status::query()->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA'])->pluck('id')->all()
                : [],
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
