<?php

namespace App\Notifications;

use App\Models\CampaignAar;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CampaignAarPublishedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public CampaignAar $aar,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->aar->loadMissing([
            'campaign',
            'event.activity',
        ]);

        return [
            'type' => 'campaign_aar_published',
            'aar_id' => $this->aar->id,
            'campaign_id' => $this->aar->campaign_id,
            'campaign_name' => $this->aar->campaign?->name ?? 'Campaña',
            'event_id' => $this->aar->event_id,
            'event_name' => $this->aar->event?->name
                ?: $this->aar->event?->activity?->name
                ?: 'Operativo',
            'event_date' => $this->aar->event?->date?->toIso8601String(),
        ];
    }
}
