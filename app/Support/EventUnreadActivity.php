<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventUnreadActivity
{
    /**
     * Fotografía de slots visibles por clave, para poder mencionar los
     * puestos o escuadras añadidos después de la última visita.
     *
     * @return array<string, array{slot:string, group:string}>
     */
    public static function slotSnapshot(\App\Models\Event $event): array
    {
        $result = [];
        foreach (($event->orbat['groups'] ?? []) as $group) {
            if (! ($group['visible'] ?? true)) {
                continue;
            }
            foreach (($group['slots'] ?? []) as $slot) {
                if (! ($slot['visible'] ?? true) || empty($slot['slot_key'])) {
                    continue;
                }
                $result[(string) $slot['slot_key']] = [
                    'slot' => (string) ($slot['name'] ?? 'Puesto'),
                    'group' => (string) ($group['name'] ?? 'Escuadra'),
                ];
            }
        }

        return $result;
    }

    /** @param Collection<int, \App\Models\Event> $events
     *  @return array<int, bool>
     */
    public static function forEvents(Collection $events, int $userId): array
    {
        $activeIds = $events
            ->filter(fn ($event): bool => EventNewsEligibility::allows($event))
            ->pluck('id')->unique()->values()->all();

        if ($activeIds === []) {
            return [];
        }

        $seen = DB::table('event_view_receipts')
            ->where('user_id', $userId)->whereIn('event_id', $activeIds)
            ->get(['event_id', 'last_viewed_at', 'visible_slot_count', 'occupied_slot_count', 'orbat_slot_snapshot'])->keyBy('event_id');

        $lastComments = DB::table('event_comments')
            ->join('events', 'events.id', '=', 'event_comments.event_id')
            ->whereIn('event_comments.event_id', $activeIds)
            ->whereNull('event_comments.deleted_at')
            ->where('event_comments.created_at', '>=', now()->subDays(\App\Services\EventNewsService::RETENTION_DAYS))
            ->where(function ($query): void {
                $query->whereNull('events.event_news_finalized_at')
                    ->orWhereColumn('event_comments.created_at', '<=', 'events.event_news_finalized_at');
            })
            ->selectRaw('event_comments.event_id, MAX(event_comments.created_at) as latest')
            ->groupBy('event_comments.event_id')->pluck('latest', 'event_comments.event_id');

        // Only new occupants count. Moves of existing occupants do not create false alerts.
        $lastAssignments = DB::table('event_slot_history')
            ->join('events', 'events.id', '=', 'event_slot_history.event_id')
            ->whereIn('event_slot_history.event_id', $activeIds)
            ->where('event_slot_history.action', 'assigned')
            ->where('event_slot_history.created_at', '>=', now()->subDays(\App\Services\EventNewsService::RETENTION_DAYS))
            ->where(function ($query): void {
                $query->whereNull('events.event_news_finalized_at')
                    ->orWhereColumn('event_slot_history.created_at', '<=', 'events.event_news_finalized_at');
            })
            ->selectRaw('event_slot_history.event_id, MAX(event_slot_history.created_at) as latest')
            ->groupBy('event_slot_history.event_id')->pluck('latest', 'event_slot_history.event_id');

        $activeEvents = $events->keyBy('id');
        $unread = [];
        foreach ($activeIds as $eventId) {
            $receipt = $seen->get($eventId);
            $visited = $receipt?->last_viewed_at;
            $comment = $lastComments->get($eventId);
            $assignment = $lastAssignments->get($eventId);
            $event = $activeEvents->get($eventId);
            $recentOrbatChange = $event?->updated_at !== null
                && $event->updated_at->greaterThanOrEqualTo(now()->subDays(\App\Services\EventNewsService::RETENTION_DAYS))
                && ($event->event_news_finalized_at === null || $event->updated_at->lessThanOrEqualTo($event->event_news_finalized_at));
            $addedSlots = $receipt && $event && $recentOrbatChange
                && $event->getOrbatSlotsCount() > (int) $receipt->visible_slot_count;
            $newSlotKeys = false;
            if ($recentOrbatChange && $receipt?->orbat_slot_snapshot) {
                $previous = (array) json_decode((string) $receipt->orbat_slot_snapshot, true);
                $current = self::slotSnapshot($activeEvents->get($eventId));
                $newSlotKeys = count(array_diff(array_keys($current), array_keys($previous))) > 0;
            }
            $addedOccupants = $receipt && $event && $recentOrbatChange
                && $event->slots
                    ->filter(fn ($slot): bool => $slot->user_id !== null || $slot->ally_id !== null)->count()
                    > (int) $receipt->occupied_slot_count;
            $unread[$eventId] = ($newSlotKeys || $addedSlots || $addedOccupants
                || ($comment && (! $visited || $comment > $visited))
                || ($assignment && (! $visited || $assignment > $visited)));
        }

        return $unread;
    }
}
