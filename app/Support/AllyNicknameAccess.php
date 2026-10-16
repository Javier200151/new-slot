<?php

namespace App\Support;

use App\Models\EventSlot;
use App\Models\User;

final class AllyNicknameAccess
{
    public static function canEdit(?User $user, EventSlot $slot): bool
    {
        if (! $user || ! $slot->ally_id) {
            return false;
        }

        if ((int) $user->ally_id === (int) $slot->ally_id
            && strtoupper(trim((string) $user->status?->name)) === 'ALIADO') {
            return true;
        }

        return $user->can('orbat.edit_ally_names');
    }
}
