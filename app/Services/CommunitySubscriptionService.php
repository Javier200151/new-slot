<?php

namespace App\Services;

use App\Models\CommunityDiary;
use App\Models\CommunityPost;
use App\Models\User;
use App\Notifications\CommunitySubscriptionUpdatedNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Notification;

class CommunitySubscriptionService
{
    public function notifyPost(CommunityPost $post, User $actor, string $kind): void
    {
        $this->notify(
            $post,
            $actor,
            'forum',
            $post->title,
            $kind,
            $post->channel,
        );
    }

    public function notifyDiary(CommunityDiary $diary, User $actor, string $kind): void
    {
        $diary->loadMissing('author');
        $authorName = $diary->author?->nick ?: $diary->author_nick ?: 'Recluta';

        $this->notify(
            $diary,
            $actor,
            'diary',
            "Diario de {$authorName}",
            $kind,
            null,
        );
    }

    private function notify(
        Model $subject,
        User $actor,
        string $subjectType,
        string $title,
        string $kind,
        ?string $channel,
    ): void {
        $users = $subject->subscriptions()
            ->with('user')
            ->where('user_id', '!=', $actor->id)
            ->get()
            ->pluck('user')
            ->filter();

        if ($users->isEmpty()) {
            return;
        }

        Notification::send(
            $users,
            new CommunitySubscriptionUpdatedNotification(
                $subjectType,
                (int) $subject->getKey(),
                $title,
                $actor,
                $kind,
                $channel,
            ),
        );
    }
}
