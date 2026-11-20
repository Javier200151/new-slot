<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Concerns\Auditable;
use Illuminate\Support\Facades\Schema;

class StatisticsSetting extends Model
{
    use Auditable;

    protected $fillable = [
        'is_enabled', 'allowed_statuses', 'allow_admins', 'show_user_profiles',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'allowed_statuses' => 'array',
            'allow_admins' => 'boolean',
            'show_user_profiles' => 'boolean',
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

    public function canViewProfile(?User $viewer): bool
    {
        return $this->show_user_profiles && $this->canView($viewer);
    }
}
