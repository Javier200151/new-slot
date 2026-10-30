<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class EventUnreadActivity
{
    /** @param Collection<int, \App\Models\Event> $events
     *  @return array<int, bool>
     */
    public static function forEvents(Collection $events, int $userId): array
    {
        $activeIds = $events
            ->filter(fn ($event): bool => strtoupper(trim((string) $event->eventStatus?->name)) === 'ACTIVO')
            ->pluck('id')->unique()->values()->all();

        if ($activeIds === []) {
            return [];
        }

        $seen = DB::table('event_view_receipts')
            ->where('user_id', $userId)->whereIn('event_id', $activeIds)
            ->get(['event_id', 'last_viewed_at', 'visible_slot_count', 'occupied_slot_count'])->keyBy('event_id');

        $lastComments = DB::table('event_comments')
            ->whereIn('event_id', $activeIds)->whereNull('deleted_at')
            ->selectRaw('event_id, MAX(created_at) as latest')
            ->groupBy('event_id')->pluck('latest', 'event_id');

        // Only new occupants count. Moves of existing occupants do not create false alerts.
        $lastAssignments = DB::table('event_slot_history')
            ->whereIn('event_id', $activeIds)->where('action', 'assigned')
            ->selectRaw('event_id, MAX(created_at) as latest')
            ->groupBy('event_id')->pluck('latest', 'event_id');

        $activeEvents = $events->keyBy('id');
        $unread = [];
        foreach ($activeIds as $eventId) {
            $receipt = $seen->get($eventId);
            $visited = $receipt?->last_viewed_at;
            $comment = $lastComments->get($eventId);
            $assignment = $lastAssignments->get($eventId);
            $addedSlots = $receipt && $activeEvents->has($eventId)
                && $activeEvents->get($eventId)->getOrbatSlotsCount() > (int) $receipt->visible_slot_count;
            $addedOccupants = $receipt && $activeEvents->has($eventId)
                && $activeEvents->get($eventId)->slots
                    ->filter(fn ($slot): bool => $slot->user_id !== null || $slot->ally_id !== null)->count()
                    > (int) $receipt->occupied_slot_count;
            $unread[$eventId] = ($addedSlots || $addedOccupants
                || ($comment && (! $visited || $comment > $visited))
                || ($assignment && (! $visited || $assignment > $visited)));
        }

        return $unread;
    }
}
