<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class EventOrbatMarkingService
{
    public const COLORS = ['white', 'green', 'yellow', 'red', 'blue'];

    /** @return array<string, array{group:string, name:string}> */
    public function visibleSlots(Event $event): array
    {
        $slots = [];
        foreach (($event->orbat['groups'] ?? []) as $group) {
            if (! ($group['visible'] ?? true)) continue;
            $groupName = trim((string) ($group['name'] ?? ''));
            foreach (($group['slots'] ?? []) as $slot) {
                if (! ($slot['visible'] ?? true) || empty($slot['slot_key'])) continue;
                $slots[(string) $slot['slot_key']] = [
                    'group' => $groupName,
                    'name' => (string) ($slot['name'] ?? 'Puesto'),
                ];
            }
        }
        return $slots;
    }

    /** 'none', 'group' or 'all' from the current user's assigned slot type. */
    public function permission(Event $event, ?User $user, string $group): bool
    {
        if (! $user || $event->eventStatus?->name !== 'ACTIVO') return false;

        $slots = EventSlot::query()
            ->where('event_id', $event->id)->where('user_id', $user->id)
            ->with('slotType')->get();

        if ($slots->contains(fn (EventSlot $slot): bool =>
            ($slot->slotType?->orbat_marking_permission ?? 'none') === 'all')) return true;

        return $slots->contains(fn (EventSlot $slot): bool =>
            ($slot->slotType?->orbat_marking_permission ?? 'none') === 'group'
            && mb_strtolower(trim((string) $slot->slot_group)) === mb_strtolower(trim($group)));
    }

    /** @return array<int, array<string,mixed>> */
    public function markings(Event $event): array
    {
        return DB::table('event_orbat_markings')
            ->where('event_id', $event->id)
            ->get()->keyBy('slot_key')->map(fn ($row): array => [
                'number' => $row->number !== null ? (int) $row->number : null,
                'color' => $row->color,
            ])->all();
    }

    /**
     * @param array<int, array{slot_key:string, number:?int, color:string}> $items
     * @return bool true only if something actually changed
     */
    public function save(Event $event, User $actor, string $group, array $items): bool
    {
        $visible = $this->visibleSlots($event);
        if (! $this->permission($event, $actor, $group)) {
            throw ValidationException::withMessages(['markings' => 'No tienes permiso para editar esta escuadra.']);
        }

        $groupKeys = collect($visible)->filter(fn (array $slot) => $slot['group'] === $group)->keys()->all();
        $receivedKeys = array_column($items, 'slot_key');
        if (count($groupKeys) !== count($receivedKeys)
            || count(array_unique($receivedKeys)) !== count($receivedKeys)
            || array_diff($groupKeys, $receivedKeys)
            || array_diff($receivedKeys, $groupKeys)) {
            throw ValidationException::withMessages(['markings' => 'La lista de puestos no coincide con el ORBAT actual.']);
        }

        $occupied = EventSlot::query()->where('event_id', $event->id)
            ->whereIn('slot_key', $groupKeys)->get()->keyBy('slot_key');
        $numbers = [];
        foreach ($items as $item) {
            if (! in_array($item['color'], self::COLORS, true)) {
                throw ValidationException::withMessages(['markings' => 'Color no permitido.']);
            }
            if (! $occupied->has($item['slot_key']) && ($item['number'] !== null || $item['color'] !== 'white')) {
                throw ValidationException::withMessages(['markings' => 'Los puestos vacíos no se pueden numerar.']);
            }
            if ($item['number'] !== null) {
                if (in_array($item['number'], $numbers, true)) {
                    throw ValidationException::withMessages(['markings' => 'No se puede repetir un número en la misma escuadra.']);
                }
                $numbers[] = $item['number'];
            }
        }

        return DB::transaction(function () use ($event, $actor, $group, $items, $occupied): bool {
            Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            if (! $this->permission($event->fresh('eventStatus'), $actor, $group)) {
                throw ValidationException::withMessages(['markings' => 'Tu permiso ha cambiado.']);
            }
            $existing = DB::table('event_orbat_markings')->where('event_id', $event->id)
                ->whereIn('slot_key', array_column($items, 'slot_key'))->get()->keyBy('slot_key');
            $changed = false;
            foreach ($items as $item) {
                $old = $existing->get($item['slot_key']);
                $oldNumber = $old?->number !== null ? (int) $old->number : null;
                $oldColor = $old?->color ?? 'white';
                if ($oldNumber === $item['number'] && $oldColor === $item['color']) continue;
                DB::table('event_orbat_markings')->updateOrInsert([
                    'event_id' => $event->id, 'slot_key' => $item['slot_key'],
                ], [
                    'number' => $item['number'], 'color' => $item['color'],
                    'updated_by' => $actor->id, 'updated_at' => now(),
                    'created_at' => $old?->created_at ?? now(),
                ]);
                $changed = true;
            }
            if ($changed) {
                // Recipients are snapshotted on save; visiting the event must not erase the notice.
                $recipients = $occupied->filter(fn (EventSlot $slot) =>
                    trim((string) $slot->slot_group) === $group && $slot->user_id && $slot->user_id !== $actor->id)
                    ->pluck('user_id')->unique();
                foreach ($recipients as $recipient) {
                    DB::table('event_orbat_marking_notices')->insert([
                        'event_id' => $event->id,
                        'user_id' => $recipient,
                        'actor_id' => $actor->id,
                        'group_name' => $group,
                        'created_at' => now(),
                    ]);
                }
            }
            return $changed;
        });
    }
}
