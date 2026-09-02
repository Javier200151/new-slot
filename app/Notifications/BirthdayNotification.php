<?php

namespace App\Notifications;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BirthdayNotification extends Notification
{
    use Queueable;

    public function __construct(
        public User $birthdayUser,
        public string $birthdayDate,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'birthday',
            'birthday_user_id' => $this->birthdayUser->id,
            'birthday_user_nick' => $this->birthdayUser->nick,
            'birthday_date' => $this->birthdayDate,
        ];
    }
}
