<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use Illuminate\Support\Facades\Schema;

class StatisticsSetting extends Model
{
    use Auditable;

    public const PROFILE_METRICS = [
        'favorite_role' => 'Rol más jugado',
        'last_year' => 'Eventos en los últimos 12 meses',
        'total' => 'Total de eventos',
        'different_roles' => 'Roles diferentes',
    ];

    protected $fillable = [
        'is_enabled', 'allowed_statuses', 'allow_admins', 'show_user_profiles', 'profile_metrics',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allowed_statuses' => 'array',
            'allow_admins' => 'boolean',
            'show_user_profiles' => 'boolean',
            'profile_metrics' => 'array',
        ];
    }

    public static function current(): self
    {
        try {
            if (Schema::hasTable('statistics_settings')) {
                return static::query()->first() ?? new static(['is_enabled' => false]);
            }
        } catch (\Throwable) {
            // Una migración pendiente no debe romper las páginas existentes.
        }

        return new static(['is_enabled' => false]);
    }

    public function canView(?User $viewer): bool
    {
        if (! $this->is_enabled || ! $viewer) {
            return false;
        }

        if ($this->allow_admins && $viewer->hasRole('admin')) {
            return true;
        }

        $allowed = array_map(
            fn ($value): string => mb_strtoupper(trim((string) $value)),
            (array) ($this->allowed_statuses ?? []),
        );

        return in_array(mb_strtoupper(trim((string) $viewer->status?->name)), $allowed, true);
    }

    /** El rol más jugado es obligatorio; el resto de tarjetas se configura en Filament. */
    public function profileMetricKeys(): array
    {
        $selected = (array) ($this->profile_metrics ?? ['favorite_role']);
        $selected = array_filter($selected, 'is_string');
        $allowed = array_keys(self::PROFILE_METRICS);

        return array_values(array_filter($allowed, static fn (string $key): bool =>
            $key === 'favorite_role' || in_array($key, $selected, true)
        ));
    }

    /** Las fichas son visibles para cualquier usuario identificado, sin filtro de estado. */
    public function canViewProfile(?User $viewer): bool
    {
        return (bool) ($this->is_enabled && $this->show_user_profiles && $viewer !== null);
    }
}
