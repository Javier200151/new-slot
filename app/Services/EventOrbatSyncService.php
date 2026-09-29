<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\User;
use App\Notifications\EventSlotChangedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventOrbatSyncService
{
    public function preview(Event $event): array
    {
        $event->loadMissing(['activity', 'slots.user', 'slots.ally']);

        if (! $event->activity) {
            throw ValidationException::withMessages([
                'orbat' => 'El evento no tiene una actividad asociada.',
            ]);
        }

        $newOrbat = $event->activity->orbat ?? ['groups' => []];
        $slotMap = $this->slotMap($newOrbat);
        $this->assertValidSlotKeys($newOrbat, $slotMap);

        $removedAssignments = $event->slots
            ->filter(fn (EventSlot $slot): bool => ! $slotMap->has((string) $slot->slot_key))
            ->filter(fn (EventSlot $slot): bool => filled($slot->user_id) || filled($slot->ally_id))
            ->map(fn (EventSlot $slot): array => [
                'event_slot_id' => (int) $slot->id,
                'slot_key' => (string) $slot->slot_key,
                'slot_name' => (string) $slot->name,
                'slot_group' => (string) $slot->slot_group,
                'user_id' => $slot->user_id ? (int) $slot->user_id : null,
                'occupant' => $slot->user?->nick ?: $slot->ally?->name ?: 'Ocupante',
            ])
            ->values()
            ->all();

        $metadataUpdates = $event->slots
            ->filter(fn (EventSlot $slot): bool => $slotMap->has((string) $slot->slot_key))
            ->filter(function (EventSlot $slot) use ($slotMap): bool {
                $target = $slotMap->get((string) $slot->slot_key);

                return (string) $slot->name !== (string) $target['name']
                    || (int) $slot->slot_type_id !== (int) $target['slot_type_id']
                    || (string) $slot->slot_group !== (string) $target['slot_group']
                    || (int) $slot->faction_id !== (int) $target['faction_id'];
            })
            ->count();

        $currentKeys = collect($event->orbat['groups'] ?? [])
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_key')
            ->filter()
            ->map(fn ($key): string => (string) $key)
            ->unique();

        return [
            'new_orbat' => $newOrbat,
            'removed_assignments' => $removedAssignments,
            'metadata_updates' => $metadataUpdates,
            'new_slots' => $slotMap->keys()->diff($currentKeys)->count(),
        ];
    }

    public function confirmationText(Event $event): string
    {
        $preview = $this->preview($event);
        $removed = $preview['removed_assignments'];

        if ($removed === []) {
            return 'Se copiará el ORBAT actual de la actividad en este evento. Se mantendrán las personas ya apuntadas siempre que su hueco siga existiendo, y se actualizarán los grupos y puestos si han cambiado. ¿Seguro que quieres continuar?';
        }

        $names = collect($removed)
            ->take(8)
            ->map(fn (array $row): string => $row['occupant'].' ('.$row['slot_group'].' · '.$row['slot_name'].')')
            ->implode(' · ');

        $extra = count($removed) > 8
            ? ' · y '.(count($removed) - 8).' más'
            : '';

        return 'Atención: hay '.count($removed).' persona(s) apuntadas en huecos que ya no existen en la actividad: '
            .$names.$extra
            .'. Si continúas, esas asignaciones se quitarán del evento. El resto de inscritos se mantendrá. ¿Seguro que quieres sincronizarlo?';
    }

    public function sync(Event $event, User $actor): array
    {
        app(CommunityRouletteService::class)->assertEventUnlocked($event);

        $notifications = [];

        $result = DB::transaction(function () use ($event, $actor, &$notifications): array {
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->with(['activity'])
                ->lockForUpdate()
                ->firstOrFail();

            app(CommunityRouletteService::class)->assertEventUnlocked($lockedEvent);

            if (! $lockedEvent->activity) {
                throw ValidationException::withMessages([
                    'orbat' => 'El evento no tiene una actividad asociada.',
                ]);
            }

            $newOrbat = $lockedEvent->activity->orbat ?? ['groups' => []];
            $slotMap = $this->slotMap($newOrbat);
            $this->assertValidSlotKeys($newOrbat, $slotMap);

            $slots = EventSlot::query()
                ->where('event_id', $lockedEvent->id)
                ->with(['user', 'ally', 'faction'])
                ->lockForUpdate()
                ->get();

            $updated = 0;
            $removed = 0;

            foreach ($slots as $eventSlot) {
                $target = $slotMap->get((string) $eventSlot->slot_key);

                if ($target) {
                    $changed = (string) $eventSlot->name !== (string) $target['name']
                        || (int) $eventSlot->slot_type_id !== (int) $target['slot_type_id']
                        || (string) $eventSlot->slot_group !== (string) $target['slot_group']
                        || (int) $eventSlot->faction_id !== (int) $target['faction_id'];

                    if ($changed) {
                        $eventSlot->forceFill([
                            'name' => $target['name'],
                            'slot_type_id' => $target['slot_type_id'],
                            'slot_group' => $target['slot_group'],
                            'faction_id' => $target['faction_id'],
                        ])->save();
                        $updated++;
                    }

                    continue;
                }

                if ($eventSlot->user_id || $eventSlot->ally_id) {
                    EventSlotHistory::query()->create([
                        'event_slot_id' => $eventSlot->id,
                        'event_id' => $lockedEvent->id,
                        'user_id' => $eventSlot->user_id,
                        'ally_id' => $eventSlot->ally_id,
                        'action' => 'unassigned',
                        'from_slot_key' => $eventSlot->slot_key,
                        'from_slot_name' => $eventSlot->name,
                        'from_slot_type_id' => $eventSlot->slot_type_id,
                        'from_slot_group' => $eventSlot->slot_group,
                        'from_army_id' => $eventSlot->faction?->army_id,
                        'to_slot_key' => null,
                        'to_slot_name' => null,
                        'to_slot_type_id' => null,
                        'to_slot_group' => null,
                        'to_army_id' => null,
                        'changed_by_user_id' => $actor->id,
                        'created_at' => now(),
                    ]);

                    if ($eventSlot->user && (int) $eventSlot->user_id !== (int) $actor->id) {
                        $notifications[] = [
                            'user' => $eventSlot->user,
                            'from_name' => $eventSlot->name,
                            'from_group' => $eventSlot->slot_group,
                        ];
                    }
                }

                $eventSlot->delete();
                $removed++;
            }

            $lockedEvent->forceFill([
                'orbat' => $newOrbat,
            ])->save();

            return [
                'updated_assignments' => $updated,
                'removed_slots' => $removed,
                'groups' => count($newOrbat['groups'] ?? []),
                'slots' => $slotMap->count(),
            ];
        });

        foreach ($notifications as $notification) {
            $notification['user']->notify(
                new EventSlotChangedNotification(
                    event: $event->fresh(['activity']),
                    action: 'removed',
                    changedBy: $actor,
                    fromSlotName: $notification['from_name'],
                    fromSlotGroup: $notification['from_group'],
                )
            );
        }

        return $result;
    }

    private function slotMap(array $orbat): Collection
    {
        return collect($orbat['groups'] ?? [])
            ->flatMap(function (array $group): array {
                $groupName = (string) ($group['name'] ?? 'Grupo sin nombre');
                $factionId = (int) ($group['faction_id'] ?? 0);

                return collect($group['slots'] ?? [])
                    ->map(function (array $slot) use ($groupName, $factionId): array {
                        return [
                            'slot_key' => trim((string) ($slot['slot_key'] ?? '')),
                            'name' => (string) ($slot['name'] ?? 'Slot sin nombre'),
                            'slot_type_id' => (int) ($slot['slot_type_id'] ?? 0),
                            'slot_group' => $groupName,
                            'faction_id' => $factionId,
                        ];
                    })
                    ->all();
            })
            ->filter(fn (array $slot): bool => $slot['slot_key'] !== '')
            ->keyBy('slot_key');
    }

    private function assertValidSlotKeys(array $orbat, Collection $slotMap): void
    {
        $slots = collect($orbat['groups'] ?? [])
            ->flatMap(fn (array $group): array => $group['slots'] ?? []);
        $keys = $slots
            ->pluck('slot_key')
            ->map(fn ($key): string => trim((string) $key));

        if ($keys->contains('') || $keys->filter()->count() !== $keys->filter()->unique()->count()) {
            throw ValidationException::withMessages([
                'orbat' => 'El ORBAT de la actividad contiene slots sin slot_key o claves duplicadas. Guarda primero el ORBAT de la actividad para regenerar claves válidas.',
            ]);
        }

        if ($slotMap->contains(fn (array $slot): bool => $slot['slot_type_id'] < 1 || $slot['faction_id'] < 1)) {
            throw ValidationException::withMessages([
                'orbat' => 'El ORBAT de la actividad contiene slots sin tipo o grupos sin facción. Corrígelo antes de sincronizar.',
            ]);
        }
    }
}
