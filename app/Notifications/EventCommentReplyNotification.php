<?php

namespace App\Notifications;

use App\Models\EventComment;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class EventCommentReplyNotification extends Notification
{
    use Queueable;

    public function __construct(
        public EventComment $reply,
    ) {}

    public function via(object $notifiable): array
    {
        return [
            'database',
        ];
    }

    public function toDatabase(object $notifiable): array
    {
        $this->reply->loadMissing([
            'event.operation',
            'user',
        ]);

        $event = $this->reply->event;

        return [
            'type' => 'event_comment_reply',

            'event_id' => $event?->id,

            'event_name' =>
                $event?->name
                ?: $event?->operation?->name
                ?: 'Evento',

            'comment_id' =>
                $this->reply->id,

            'parent_comment_id' =>
                $this->reply->parent_id,

            'reply_user_id' =>
                $this->reply->user?->id,

            'reply_user_nick' =>
                $this->reply->user?->nick
                ?: 'Un usuario',
        ];
    }
}