<?php

namespace App\Policies;

use App\Models\Event;
use App\Models\User;
use App\Support\ActivityTypeAccess;

class EventPolicy
{
    public function viewAny(User $user): bool
    {
        return ActivityTypeAccess::canAny(
            $user,
            'events',
            'view',
        );
    }

    public function view(User $user, Event $event): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'events',
            'view',
            $event->activity?->operation_type_id,
        );
    }

    public function create(User $user): bool
    {
        return ActivityTypeAccess::canAny(
            $user,
            'events',
            'create',
        );
    }

    public function update(User $user, Event $event): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'events',
            'update',
            $event->activity?->operation_type_id,
        );
    }

    public function delete(User $user, Event $event): bool
    {
        return ActivityTypeAccess::can(
            $user,
            'events',
            'delete',
            $event->activity?->operation_type_id,
        );
    }

    public function deleteAny(User $user): bool
    {
        // Evitamos borrados masivos mezclando tipos con permisos distintos.
        return $user->hasRole('admin');
    }
}
