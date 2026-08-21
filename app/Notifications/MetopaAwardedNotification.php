<?php

namespace App\Notifications;

use App\Models\Metopa;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class MetopaAwardedNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $awardedUser,
        public Metopa $metopa,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'metopa_awarded',

            'metopa_id' => $this->metopa->id,
            'metopa_name' => $this->metopa->name,

            'awarded_user_id' => $this->awardedUser->id,
            'awarded_user_nick' => $this->awardedUser->nick,
        ];
    }
}