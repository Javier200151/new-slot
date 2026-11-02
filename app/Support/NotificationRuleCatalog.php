<?php

namespace App\Support;

use App\Models\CommunityDiary;
use App\Models\CommunityPost;
use App\Notifications\BirthdayNotification;
use App\Notifications\CampaignAarPendingNotification;
use App\Notifications\CampaignAarPublishedNotification;
use App\Notifications\ClipYearPhaseNotification;
use App\Notifications\CommunityRouletteWinnerNotification;
use App\Notifications\CommunitySubscriptionUpdatedNotification;
use App\Notifications\EventCommentReplyNotification;
use App\Notifications\EventPublishedNotification;
use App\Notifications\EventSlotChangedNotification;
use App\Notifications\MetopaAwardedNotification;
use Carbon\CarbonInterface;
use Carbon\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Notifications\Notification;

/** Catálogo único de notificaciones internas (los correos de seguridad no se desactivan). */
final class NotificationRuleCatalog
{
    public const TYPES = [
        'event_published' => ['label' => 'Publicación de partida', 'category' => 'Partidas', 'class' => EventPublishedNotification::class, 'age' => true, 'default' => 6],
        'event_slot_assigned' => ['label' => 'Asignación a un puesto del ORBAT', 'category' => 'Partidas', 'class' => EventSlotChangedNotification::class, 'age' => true, 'default' => 6],
        'event_slot_moved' => ['label' => 'Movimiento entre puestos del ORBAT', 'category' => 'Partidas', 'class' => EventSlotChangedNotification::class, 'age' => true, 'default' => 6],
        'event_slot_removed' => ['label' => 'Retirada del ORBAT', 'category' => 'Partidas', 'class' => EventSlotChangedNotification::class, 'age' => true, 'default' => 6],
        'event_slot_changed' => ['label' => 'Otros cambios del ORBAT', 'category' => 'Partidas', 'class' => EventSlotChangedNotification::class, 'age' => true, 'default' => 6],
        'event_comment_reply' => ['label' => 'Respuestas a comentarios de partidas', 'category' => 'Partidas', 'class' => EventCommentReplyNotification::class, 'age' => true, 'default' => null],
        'campaign_aar_pending' => ['label' => 'AAR pendiente de campaña', 'category' => 'Campañas', 'class' => CampaignAarPendingNotification::class, 'age' => true, 'default' => 6],
        'campaign_aar_published' => ['label' => 'AAR publicado de campaña', 'category' => 'Campañas', 'class' => CampaignAarPublishedNotification::class, 'age' => true, 'default' => 6],
        'community_roulette_winner' => ['label' => 'Resultado de la ruleta', 'category' => 'Partidas', 'class' => CommunityRouletteWinnerNotification::class, 'age' => true, 'default' => null],
        'subscription_forum' => ['label' => 'Novedades de temas seguidos del foro', 'category' => 'Comunidad', 'class' => CommunitySubscriptionUpdatedNotification::class, 'age' => true, 'default' => null],
        'subscription_diary' => ['label' => 'Novedades de diarios seguidos', 'category' => 'Comunidad', 'class' => CommunitySubscriptionUpdatedNotification::class, 'age' => true, 'default' => null],
        'community_subscription_update' => ['label' => 'Otras publicaciones seguidas', 'category' => 'Comunidad', 'class' => CommunitySubscriptionUpdatedNotification::class, 'age' => true, 'default' => null],
        'birthday' => ['label' => 'Cumpleaños de miembros', 'category' => 'Comunidad', 'class' => BirthdayNotification::class, 'age' => false, 'default' => null],
        'metopa_awarded' => ['label' => 'Concesión de metopas', 'category' => 'Comunidad', 'class' => MetopaAwardedNotification::class, 'age' => false, 'default' => null],
        'clip_year_nominations' => ['label' => 'Apertura de candidaturas', 'category' => 'Clip Awards', 'class' => ClipYearPhaseNotification::class, 'age' => true, 'default' => null],
        'clip_year_voting' => ['label' => 'Apertura de votaciones', 'category' => 'Clip Awards', 'class' => ClipYearPhaseNotification::class, 'age' => true, 'default' => null],
    ];

    public static function keyFor(Notification $notification): ?string
    {
        if ($notification instanceof EventSlotChangedNotification) {
            return match ($notification->action) {
                'assigned' => 'event_slot_assigned',
                'moved' => 'event_slot_moved',
                'removed' => 'event_slot_removed',
                default => 'event_slot_changed',
            };
        }
        if ($notification instanceof CommunitySubscriptionUpdatedNotification) {
            return match ($notification->subjectType) {
                'forum' => 'subscription_forum',
                'diary' => 'subscription_diary',
                default => 'community_subscription_update',
            };
        }
        if ($notification instanceof ClipYearPhaseNotification) {
            return $notification->phase === 'nominations' ? 'clip_year_nominations' : 'clip_year_voting';
        }
        foreach (self::TYPES as $key => $item) {
            if ($notification instanceof $item['class']) {
                return $key;
            }
        }
        return null;
    }

    /** Fecha del contenido de origen; nunca la fecha de subida/importación. */
    public static function dateFor(Notification $notification): ?CarbonInterface
    {
        if ($notification instanceof EventPublishedNotification || $notification instanceof EventSlotChangedNotification) {
            return self::eventDate($notification->event);
        }
        if ($notification instanceof EventCommentReplyNotification) {
            return self::eventDate($notification->reply->event);
        }
        if ($notification instanceof CampaignAarPendingNotification || $notification instanceof CampaignAarPublishedNotification) {
            return self::eventDate($notification->aar->event);
        }
        if ($notification instanceof CommunityRouletteWinnerNotification) {
            return self::eventDate($notification->room->event);
        }
        if ($notification instanceof CommunitySubscriptionUpdatedNotification) {
            $cacheKey = 'sqa.notification-source.' . $notification->subjectType . '.' . $notification->subjectId;
            $date = Cache::remember($cacheKey, now()->addMinute(), function () use ($notification): ?string {
                $subject = match ($notification->subjectType) {
                    'forum' => CommunityPost::query()->find($notification->subjectId),
                    'diary' => CommunityDiary::query()->find($notification->subjectId),
                    default => null,
                };
                return $subject?->created_at?->toIso8601String();
            });
            return $date ? Carbon::parse($date) : null;
        }
        if ($notification instanceof ClipYearPhaseNotification) {
            return $notification->phase === 'nominations'
                ? $notification->contest->nomination_starts_at
                : $notification->contest->voting_starts_at;
        }
        return null;
    }

    public static function eventDate($event): ?CarbonInterface
    {
        return $event?->end_date ?: $event?->date;
    }
}
