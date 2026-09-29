<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\Faction;
use App\Models\SlotType;
use Illuminate\Http\JsonResponse;

class EventOrbatController extends Controller
{
    /**
     * Devuelve el estado actual del ORBAT y de la cola de reservas.
     *
     * GET /api/eventos/{event}/orbat
     *
     * Se consulta directamente la base de datos en cada petición y la respuesta
     * se marca como no-cache para que consumidores externos no reutilicen una
     * versión anterior del ORBAT.
     */
    public function show(int $event): JsonResponse
    {
        $eventModel = Event::query()
            ->with([
                'eventStatus:id,name',
                'activity:id,name',
                'slots.user.status',
                'slots.ally',
                'slots.slotType:id,name',
                'reservations.user.status',
            ])
            ->findOrFail($event);

        $orbat = $eventModel->orbat ?? ['groups' => []];
        $groups = collect($orbat['groups'] ?? []);

        $slotTypeIds = $groups
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_type_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $factionIds = $groups
            ->pluck('faction_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $slotTypes = $slotTypeIds->isEmpty()
            ? collect()
            : SlotType::query()
                ->whereIn('id', $slotTypeIds)
                ->pluck('name', 'id');

        $factions = $factionIds->isEmpty()
            ? collect()
            : Faction::query()
                ->whereIn('id', $factionIds)
                ->pluck('name', 'id');

        $eventSlots = $eventModel->slots->values();
        $slotsByKey = $eventSlots
            ->filter(fn (EventSlot $slot): bool => filled($slot->slot_key))
            ->keyBy(fn (EventSlot $slot): string => (string) $slot->slot_key);

        $usedEventSlotIds = collect();
        $orbatSlotCount = 0;
        $orbatOccupiedCount = 0;

        $serializedGroups = $groups
            ->map(function (array $group) use (
                $slotsByKey,
                $eventSlots,
                $slotTypes,
                $factions,
                $usedEventSlotIds,
                &$orbatSlotCount,
                &$orbatOccupiedCount,
            ): array {
                $groupName = trim((string) ($group['name'] ?? ''));
                $factionId = filled($group['faction_id'] ?? null)
                    ? (int) $group['faction_id']
                    : null;

                $serializedSlots = collect($group['slots'] ?? [])
                    ->map(function (array $slot) use (
                        $groupName,
                        $factionId,
                        $slotsByKey,
                        $eventSlots,
                        $slotTypes,
                        $usedEventSlotIds,
                        &$orbatSlotCount,
                        &$orbatOccupiedCount,
                    ): array {
                        $orbatSlotCount++;

                        $slotKey = trim((string) ($slot['slot_key'] ?? ''));
                        $slotTypeId = filled($slot['slot_type_id'] ?? null)
                            ? (int) $slot['slot_type_id']
                            : null;
                        $slotName = trim((string) ($slot['name'] ?? ''));

                        $assignment = $slotKey !== ''
                            ? $slotsByKey->get($slotKey)
                            : null;

                        // Compatibilidad de solo lectura para ORBAT antiguos sin
                        // slot_key: buscamos una única coincidencia por metadatos.
                        if (! $assignment) {
                            $assignment = $eventSlots
                                ->filter(fn (EventSlot $candidate): bool =>
                                    ! $usedEventSlotIds->contains((int) $candidate->id)
                                    && trim((string) $candidate->slot_group) === $groupName
                                    && trim((string) $candidate->name) === $slotName
                                    && (
                                        $slotTypeId === null
                                        || (int) $candidate->slot_type_id === $slotTypeId
                                    )
                                    && (
                                        $factionId === null
                                        || (int) $candidate->faction_id === $factionId
                                    )
                                )
                                ->first();
                        }

                        if ($assignment) {
                            $usedEventSlotIds->push((int) $assignment->id);
                        }

                        $occupant = $this->occupantPayload($assignment);
                        if ($occupant !== null) {
                            $orbatOccupiedCount++;
                        }

                        return [
                            'slot_key' => $slotKey !== '' ? $slotKey : null,
                            'name' => $slotName !== '' ? $slotName : null,
                            'visible' => (bool) ($slot['visible'] ?? true),
                            'slot_type' => $slotTypeId
                                ? [
                                    'id' => $slotTypeId,
                                    'name' => $slotTypes[$slotTypeId] ?? null,
                                ]
                                : null,
                            'occupant' => $occupant,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'name' => $groupName !== '' ? $groupName : null,
                    'visible' => (bool) ($group['visible'] ?? true),
                    'faction' => $factionId
                        ? [
                            'id' => $factionId,
                            'name' => $factions[$factionId] ?? null,
                        ]
                        : null,
                    'slots' => $serializedSlots,
                ];
            })
            ->values()
            ->all();

        // Si existiese una asignación actual que ya no encaja en el snapshot
        // del ORBAT, no la ocultamos: la devolvemos aparte para garantizar que
        // la API contiene a todos los apuntados actuales.
        $unmatchedAssignments = $eventSlots
            ->filter(fn (EventSlot $slot): bool =>
                ! $usedEventSlotIds->contains((int) $slot->id)
                && ($slot->user_id !== null || $slot->ally_id !== null)
            )
            ->map(fn (EventSlot $slot): array => [
                'event_slot_id' => (int) $slot->id,
                'slot_key' => filled($slot->slot_key) ? (string) $slot->slot_key : null,
                'group' => filled($slot->slot_group) ? (string) $slot->slot_group : null,
                'name' => filled($slot->name) ? (string) $slot->name : null,
                'slot_type' => $slot->slotType
                    ? [
                        'id' => (int) $slot->slotType->id,
                        'name' => (string) $slot->slotType->name,
                    ]
                    : null,
                'occupant' => $this->occupantPayload($slot),
            ])
            ->values()
            ->all();

        $reservations = $eventModel->reservations
            ->filter(fn ($reservation): bool => $reservation->user !== null)
            ->values()
            ->map(fn ($reservation, int $index): array => [
                'position' => $index + 1,
                'reservation_id' => (int) $reservation->id,
                'user' => $this->userPayload($reservation->user),
                'reserved_at' => $reservation->created_at?->toIso8601String(),
            ])
            ->all();

        $assignedTotal = $eventSlots
            ->filter(fn (EventSlot $slot): bool =>
                $slot->user_id !== null || $slot->ally_id !== null
            )
            ->count();

        activity('audit')
            ->event('api_event_orbat_read')
            ->performedOn($eventModel)
            ->withProperties([
                'client' => 'external_api',
                'event_id' => (int) $eventModel->id,
                'assigned' => $assignedTotal,
                'reservations' => count($reservations),
            ])
            ->log('api_event_orbat_read');

        return response()
            ->json([
                'data' => [
                    'event' => [
                        'id' => (int) $eventModel->id,
                        'name' => (string) $eventModel->name,
                        'activity' => $eventModel->activity
                            ? [
                                'id' => (int) $eventModel->activity->id,
                                'name' => (string) $eventModel->activity->name,
                            ]
                            : null,
                        'status' => $eventModel->eventStatus
                            ? [
                                'id' => (int) $eventModel->eventStatus->id,
                                'name' => (string) $eventModel->eventStatus->name,
                            ]
                            : null,
                        'date' => $eventModel->date?->toIso8601String(),
                        'end_date' => $eventModel->end_date?->toIso8601String(),
                        'reservations_enabled' => (bool) $eventModel->reservations_enabled,
                    ],
                    'orbat' => [
                        'groups' => $serializedGroups,
                        'unmatched_assignments' => $unmatchedAssignments,
                    ],
                    'reservations' => $reservations,
                    'counts' => [
                        'groups' => count($serializedGroups),
                        'slots' => $orbatSlotCount,
                        'occupied_in_orbat' => $orbatOccupiedCount,
                        'assigned_total' => $assignedTotal,
                        'unmatched_assignments' => count($unmatchedAssignments),
                        'reservations' => count($reservations),
                    ],
                    'generated_at' => now()->toIso8601String(),
                ],
            ])
            ->withHeaders([
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
                'Pragma' => 'no-cache',
            ]);
    }

    private function occupantPayload(?EventSlot $slot): ?array
    {
        if (! $slot) {
            return null;
        }

        if ($slot->user) {
            return [
                'type' => 'user',
                'user' => $this->userPayload($slot->user),
            ];
        }

        if ($slot->ally) {
            return [
                'type' => 'ally',
                'ally' => [
                    'id' => (int) $slot->ally->id,
                    'name' => (string) $slot->ally->name,
                ],
            ];
        }

        return null;
    }

    private function userPayload($user): array
    {
        return [
            'id' => (int) $user->id,
            'nick' => (string) $user->nick,
            'status' => [
                'id' => $user->status?->id ? (int) $user->status->id : null,
                'name' => $user->status?->name,
                'color' => $user->getStatusColor(),
            ],
        ];
    }
}
