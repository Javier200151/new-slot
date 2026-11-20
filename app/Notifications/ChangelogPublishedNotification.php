<?php

namespace App\Notifications;

use App\Models\ChangelogEntry;
use Illuminate\Notifications\Notification;

class ChangelogPublishedNotification extends Notification
{
    public function __construct(public ChangelogEntry $entry) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toDatabase(object $notifiable): array
    {
        return [
            'type' => 'changelog_published',
            'changelog_id' => $this->entry->id,
            'version' => $this->entry->version,
            'release_date' => $this->entry->release_date?->toDateString(),
        ];
    }
}
