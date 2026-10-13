<?php

namespace App\Support;

use App\Models\Event;
use App\Models\User;

/** Permisos de asignación, independientes del diseño del selector ORBAT. */
class OrbatAssignmentAccess
{
    public static function hasAny(?User $actor, Event $event): bool
    {
        if (! $actor) {
            return false;
        }

        $typeId = $event->activity?->activity_type_id;
        if (! $typeId) {
            return false;
        }

        return collect(PermissionCatalog::actionsFor('event-orbat'))
            ->contains(fn (string $action): bool => str_starts_with($action, 'assign-')
                && ActivityTypeAccess::can($actor, 'event-orbat', $action, $typeId));
    }

    public static function canAssignAlly(?User $actor, Event $event): bool
    {
        return self::allowed($actor, $event, 'assign-allies');
    }

    public static function canAssignUser(?User $actor, Event $event, User $candidate): bool
    {
        return $candidate->status_id !== null && self::allowed($actor, $event, 'assign-status-' . (int) $candidate->status_id);
    }

    private static function allowed(?User $actor, Event $event, string $action): bool
    {
        return ActivityTypeAccess::can($actor, 'event-orbat', $action, $event->activity?->activity_type_id);
    }
}
