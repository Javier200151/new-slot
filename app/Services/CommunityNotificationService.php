<?php

namespace App\Services;

use App\Models\Event;
use App\Models\Metopa;
use App\Models\User;
use App\Notifications\EventPublishedNotification;
use App\Notifications\MetopaAwardedNotification;
use Illuminate\Notifications\DatabaseNotification;
use Illuminate\Support\Facades\Notification;

class CommunityNotificationService
{
    public function eventPublished(
        Event $event,
    ): void {
        /*
         * Evita mandar dos veces la misma publicación.
         *
         * Por ejemplo:
         *
         * ACTIVO -> FINALIZADO -> ACTIVO
         */
        $alreadySent = DatabaseNotification::query()
            ->where(
                'type',
                EventPublishedNotification::class,
            )
            ->where(
                'data->event_id',
                $event->id,
            )
            ->exists();

        if ($alreadySent) {
            return;
        }

        User::query()
            ->select('id')
            ->chunkById(
                200,
                function ($users) use ($event): void {
                    Notification::send(
                        $users,
                        new EventPublishedNotification(
                            $event
                        ),
                    );
                }
            );
    }

    public function metopaAwarded(
        User $awardedUser,
        Metopa $metopa,
    ): void {
        User::query()
            ->select('id')
            ->chunkById(
                200,
                function ($users) use (
                    $awardedUser,
                    $metopa,
                ): void {
                    Notification::send(
                        $users,
                        new MetopaAwardedNotification(
                            $awardedUser,
                            $metopa,
                        ),
                    );
                }
            );
    }
}