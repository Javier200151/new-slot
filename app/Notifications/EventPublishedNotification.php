<?php

namespace App\Notifications;

use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public Event $event,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->event->loadMissing('operation');

        return [
            'type' => 'event_published',

            'event_id' => $this->event->id,

            'event_name' =>
                $this->event->name
                ?: $this->event->operation?->name
                ?: 'Evento',

            'event_date' =>
                $this->event->date?->toIso8601String(),
        ];
    }
}