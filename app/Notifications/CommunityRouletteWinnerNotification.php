<?php

namespace App\Notifications;

use App\Models\CommunityRouletteRoom;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityRouletteWinnerNotification extends Notification
{
    use Queueable;

    public function __construct(public CommunityRouletteRoom $room)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->room->loadMissing(['event.activity', 'targetSlotType']);

        return [
            'type' => 'community_roulette_winner',
            'room_id' => $this->room->id,
            'event_id' => $this->room->event_id,
            'event_name' => $this->room->event?->name
                ?: $this->room->event?->activity?->name
                ?: 'Evento',
            'slot_name' => $this->room->target_slot_name,
            'slot_group' => $this->room->target_slot_group,
            'slot_type' => $this->room->targetSlotType?->name,
            'phrase' => $this->room->winner_phrase_text,
        ];
    }
}
