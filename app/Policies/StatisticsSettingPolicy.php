<?php

namespace App\Policies;

use App\Models\StatisticsSetting;
use App\Models\User;

/** Las estadísticas públicas son configurables exclusivamente por administradores. */
class StatisticsSettingPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasRole('admin');
    }

    public function view(User $user, StatisticsSetting $record): bool
    {
        return $this->viewAny($user);
    }

    public function update(User $user, StatisticsSetting $record): bool
    {
        return $this->viewAny($user);
    }

    public function create(User $user): bool { return false; }
    public function delete(User $user, StatisticsSetting $record): bool { return false; }
    public function deleteAny(User $user): bool { return false; }
}
