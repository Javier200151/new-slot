<?php

namespace App\Services;

use App\Models\CommunityDiary;
use App\Models\CommunityPost;
use App\Models\User;
use App\Support\CommunityArea;
use App\Support\CommunityForumCategory;
use Illuminate\Database\Eloquent\Builder;

/**
 * Usa el mismo criterio de mensajes sin leer que las secciones Foro y Eventos.
 * Los eventos se contabilizan por evento con actividad pendiente, no por slot.
 */
class QuickAccessNewsService
{
    public static function canSeeForum(User $user): bool
    {
        return CommunityArea::hasArea($user);
    }

    public static function canSeeEvents(User $user): bool
    {
        return $user->status?->name === 'ACTIVO' || $user->hasRole('admin');
    }

    public static function canSeeAny(User $user): bool
    {
        return self::canSeeForum($user) || self::canSeeEvents($user);
    }

    /** @return array{forum:int,events:int,total:int} */
    public function counts(User $user): array
    {
        $forum = self::canSeeForum($user) ? $this->forum($user) : 0;
        $events = self::canSeeEvents($user) ? $this->events($user) : 0;

        return ['forum' => $forum, 'events' => $events, 'total' => $forum + $events];
    }

    private function forum(User $user): int
    {
        // Equivalente a CommunityForumController::unreadForumCount().
        $keys = collect(CommunityForumCategory::landing())
            ->filter(fn (array $category, string $key): bool =>
                ($category['channel'] ?? null) !== 'diary'
                && CommunityForumCategory::canView($user, $key))
            ->keys()->values();

        $query = CommunityPost::query();
        if ($keys->isEmpty()) {
            $query->whereRaw('1 = 0');
        } else {
            $query->where(function (Builder $visible) use ($keys): void {
                foreach ($keys as $key) {
                    $visible->orWhere(function (Builder $categoryQuery) use ($key): void {
                        CommunityForumCategory::applyToQuery($categoryQuery, (string) $key);
                    });
                }
            });
        }

        $count = $query->unreadFor($user)->count();
        if (CommunityForumCategory::canView($user, CommunityForumCategory::DIARY)) {
            $count += CommunityDiary::query()->unreadFor($user)->count();
        }

        return (int) $count;
    }

    private function events(User $user): int
    {
        // La fuente de verdad es la misma que alimenta /eventos/nuevos-mensajes.
        // Una tarjeta pendiente por evento, aunque contenga varios comentarios o inscripciones.
        return app(EventNewsService::class)->forUser($user)
            ->filter(fn (array $event): bool => (bool) $event['unread'])
            ->count();
    }
}
