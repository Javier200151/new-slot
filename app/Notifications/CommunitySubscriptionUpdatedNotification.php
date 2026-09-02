<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class CommunitySubscriptionUpdatedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public string $subjectType,
        public int $subjectId,
        public string $subjectTitle,
        public ?User $actor,
        public string $updateKind,
        public ?string $channel = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'community_subscription_update',
            'subject_type' => $this->subjectType,
            'subject_id' => $this->subjectId,
            'subject_title' => $this->subjectTitle,
            'channel' => $this->channel,
            'update_kind' => $this->updateKind,
            'actor_user_id' => $this->actor?->id,
            'actor_nick' => $this->actor?->nick ?: 'Un usuario',
        ];
    }
}
