<?php

namespace App\Notifications;

use App\Models\Event;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventSlotChangedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
        public string $action,
        public User $changedBy,

        public ?string $fromSlotName = null,
        public ?string $fromSlotGroup = null,

        public ?string $toSlotName = null,
        public ?string $toSlotGroup = null,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->event->loadMissing('activity');

        return [
            'type' => 'event_slot_changed',

            'action' => $this->action,

            'event_id' => $this->event->id,

            'event_name' =>
                $this->event->name
                ?: $this->event->activity?->name
                ?: 'Evento',

            'changed_by_user_id' =>
                $this->changedBy->id,

            'changed_by_user_nick' =>
                $this->changedBy->nick
                ?: 'Un administrador',

            'from_slot_name' =>
                $this->fromSlotName,

            'from_slot_group' =>
                $this->fromSlotGroup,

            'to_slot_name' =>
                $this->toSlotName,

            'to_slot_group' =>
                $this->toSlotGroup,
        ];
    }
}