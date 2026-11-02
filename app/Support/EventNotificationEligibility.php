<?php

namespace App\Support;

use App\Models\Event;

/** Aplicación temprana de los ajustes de Filament a eventos y AAR. */
final class EventNotificationEligibility
{
    public static function allows(?Event $event, string $type = 'event_published'): bool
    {
        if (! $event) return false;

        $eventDate = NotificationRuleCatalog::eventDate($event);
        if (! $eventDate) return false;

        return NotificationRulePolicy::allows($type, $eventDate);
    }
}
