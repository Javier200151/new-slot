<?php

namespace App\Support;

use App\Models\User;

final class ChangelogAccess
{
    public static function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        return in_array(
            strtoupper(trim((string) $user->status?->name)),
            ['RECLUTA', 'ACTIVO', 'RESERVA'],
            true,
        );
    }
}
