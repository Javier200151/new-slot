<?php

namespace App\Notifications;

use App\Models\CommunityRaffleDraw;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunityRafflePrizeNotification extends Notification
{
    use Queueable;

    public function __construct(public CommunityRaffleDraw $draw, public bool $cancelled = false, public bool $changed = false)
    {
    }

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => $this->cancelled ? 'community_raffle_cancelled' : ($this->changed ? 'community_raffle_changed' : 'community_raffle_prize'),
            'raffle_id' => $this->draw->raffle_id,
            'draw_id' => $this->draw->id,
            'raffle_title' => $this->draw->raffle?->title ?? 'Sorteo',
            'prize_name' => $this->draw->prize_name,
            'phrase' => $this->cancelled ? null : $this->draw->phrase,
        ];
    }
}
