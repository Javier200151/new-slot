<?php

namespace App\Policies;

use App\Models\Activity;
use App\Models\User;
use App\Support\ActivityTypeAccess;

/**
 * Policy canónica para las actividades de NewSlot.
 *
 * Usa las claves canónicas `activities.type.*`; la migración de permisos conserva
 * los IDs y las asignaciones existentes de Spatie Permission.
 */
class ActivityPolicy
{
    public function viewAny(User $user): bool
    {
        return ActivityTypeAccess::canAny(
            $user,
            'activities',
            'view',
        );
    }

    public function view(User $user, Activity $activity): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'activities',
            'view',
            $activity->operation_type_id,
        );
    }

    public function create(User $user): bool
    {
        return ActivityTypeAccess::canAny(
            $user,
            'activities',
            'create',
        );
    }

    public function update(User $user, Activity $activity): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'activities',
            'update',
            $activity->operation_type_id,
        );
    }

    public function delete(User $user, Activity $activity): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'activities',
            'delete',
            $activity->operation_type_id,
        );
    }

    public function deleteAny(User $user): bool
    {
        // Evitamos borrados masivos mezclando tipos con permisos distintos.
        return $user->hasRole('admin');
    }
}
