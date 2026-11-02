<?php

namespace App\Listeners;

use App\Support\NotificationRulePolicy;
use Illuminate\Notifications\Events\NotificationSending;

class FilterInternalNotification
{
    public function handle(NotificationSending $event): bool
    {
        if ($event->channel !== 'database') {
            return true;
        }

        return NotificationRulePolicy::allowsNotification($event->notification);
    }
}
