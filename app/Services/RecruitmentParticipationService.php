<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class RecruitmentParticipationService
{
    public function playedEvents(
        int $userId,
        CarbonInterface $startedAt,
        ?CarbonInterface $endedAt = null,
    ): Collection {
        $endedAt ??= now();

        $currentEventIds = EventSlot::query()
            ->where('user_id', $userId)
            ->pluck('event_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $latestHistoryByEvent = EventSlotHistory::query()
            ->where('user_id', $userId)
            ->whereNotNull('event_id')
            ->orderBy('event_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'event_id',
                'action',
                'to_slot_key',
            ])
            ->unique('event_id');

        $historicalEventIds = $latestHistoryByEvent
            ->filter(fn (EventSlotHistory $movement): bool =>
                in_array($movement->action, ['assigned', 'moved'], true)
                && filled($movement->to_slot_key)
            )
            ->pluck('event_id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        $eventIds = array_values(array_unique([
            ...$currentEventIds,
            ...$historicalEventIds,
        ]));

        if ($eventIds === []) {
            return collect();
        }

        return Event::query()
            ->with(['activity:id,name,activity_type_id', 'activity.activityType:id,name', 'eventStatus:id,name'])
            ->whereIn('id', $eventIds)
            ->whereBetween('date', [$startedAt, $endedAt])
            ->where('date', '<=', now())
            ->whereDoesntHave(
                'eventStatus',
                fn ($query) => $query->whereRaw('UPPER(TRIM(name)) = ?', ['CANCELADO'])
            )
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }


    public function playedEventDetails(
        int $userId,
        CarbonInterface $startedAt,
        ?CarbonInterface $endedAt = null,
    ): Collection {
        $events = $this->playedEvents($userId, $startedAt, $endedAt);

        if ($events->isEmpty()) {
            return collect();
        }

        $eventIds = $events->pluck('id')->map(fn ($id): int => (int) $id)->all();

        $currentSlots = EventSlot::query()
            ->with('slotType:id,name')
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->get([
                'event_id',
                'name',
                'slot_group',
                'slot_type_id',
            ])
            ->keyBy('event_id');

        $latestHistoryByEvent = EventSlotHistory::query()
            ->with('toSlotType:id,name')
            ->where('user_id', $userId)
            ->whereIn('event_id', $eventIds)
            ->orderBy('event_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get([
                'id',
                'event_id',
                'action',
                'to_slot_key',
                'to_slot_name',
                'to_slot_group',
                'to_slot_type_id',
                'created_at',
            ])
            ->unique('event_id')
            ->keyBy('event_id');

        return $events->map(function (Event $event) use ($currentSlots, $latestHistoryByEvent): array {
            $slot = $currentSlots->get($event->id);
            $movement = $latestHistoryByEvent->get($event->id);

            if ($slot) {
                $role = $slot->name ?: $slot->slotType?->name;
                $squad = $slot->slot_group;
            } elseif (
                $movement
                && in_array($movement->action, ['assigned', 'moved'], true)
                && filled($movement->to_slot_key)
            ) {
                $role = $movement->to_slot_name ?: $movement->toSlotType?->name;
                $squad = $movement->to_slot_group;
            } else {
                $role = null;
                $squad = null;
            }

            return [
                'event' => $event,
                'role' => $role ?: 'Sin rol identificado',
                'squad' => filled($squad) ? $squad : 'Sin escuadra identificada',
            ];
        });
    }

    public function countPlayedEvents(
        int $userId,
        CarbonInterface $startedAt,
        CarbonInterface $endedAt,
    ): int {
        return $this->playedEvents($userId, $startedAt, $endedAt)->count();
    }
}
