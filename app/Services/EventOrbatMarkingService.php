<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventSlot;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class EventOrbatMarkingService
{
    public const COLORS = ['white', 'green', 'yellow', 'red', 'blue'];
    /**
     * Current squads last edited by this member. Used for the opt-in departure
     * warning, including squads where they no longer occupy a slot.
     *
     * @return array<int, array{group:string,title:string,players:int}>
     */
    public function departureResetChoices(Event $event, User $user): array
    {
        if (! Schema::hasTable('event_orbat_group_settings')) return [];

        $currentGroups = collect($event->orbat['groups'] ?? [])
            ->filter(fn (array $g): bool => ($g['visible'] ?? true))
            ->pluck('name')->map(fn ($name): string => trim((string) $name))->all();

        $markings = $this->markings($event);

        return DB::table('event_orbat_group_settings')
            ->where('event_id', $event->id)
            ->where('updated_by', $user->id)
            ->get()
            ->filter(fn ($row): bool => in_array($row->group_name, $currentGroups, true))
            ->map(function ($row) use ($event, $markings): array {
                $members = EventSlot::query()->where('event_id', $event->id)
                    ->where('slot_group', $row->group_name)->with('user')->get();
                return [
                    'group' => $row->group_name,
                    'title' => (string) ($row->custom_title ?? ''),
                    'players' => $members->count(),
                    'details' => $members->map(function (EventSlot $slot) use ($markings): array {
                        $marking = $markings[$slot->slot_key] ?? [];
                        return [
                            'name' => $slot->user?->nick ?? $slot->ally?->name ?? 'Miembro',
                            'number' => $marking['number'] ?? null,
                            'color' => $marking['color'] ?? 'white',
                        ];
                    })->filter(fn (array $member): bool => $member['number'] !== null
                        || $member['color'] !== 'white')->values()->all(),
                ];
            })->values()->all();
    }

    /**
     * Called under the event's enrollment transaction just before deleting a
     * user's slot. Clearing is opt-in and restricted to groups they last edited.
     */
    public function leaving(Event $event, EventSlot $leaving, User $user, bool $resetGroups): void
    {
        if (! Schema::hasTable('event_orbat_markings')) return;

        $groupNames = $resetGroups
            ? collect($this->departureResetChoices($event, $user))->pluck('group')->all()
            : [];

        foreach ($groupNames as $group) {
            $groupSlots = EventSlot::query()->where('event_id', $event->id)
                ->where('slot_group', $group)->get();
            $keys = $groupSlots->map(fn (EventSlot $slot): ?string => $this->participantKey($slot))
                ->filter()->values()->all();
            $slotKeys = $groupSlots->pluck('slot_key')->all();
            DB::table('event_orbat_markings')->where('event_id', $event->id)
                ->where(fn ($q) => $q->whereIn('participant_key', $keys)
                    ->orWhereIn('slot_key', $slotKeys))->delete();
            DB::table('event_orbat_group_settings')
                ->where('event_id', $event->id)->where('group_name', $group)->delete();

            foreach ($groupSlots->pluck('user_id')->filter()->unique() as $recipient) {
                if ((int) $recipient === (int) $user->id) continue;
                DB::table('event_orbat_marking_notices')->insert([
                    'event_id' => $event->id,
                    'user_id' => $recipient,
                    'actor_id' => $user->id,
                    'group_name' => $group,
                    'created_at' => now(),
                ]);
            }
        }

        if ($resetGroups && in_array($leaving->slot_group, $groupNames, true)) return;

        $participantKey = $this->participantKey($leaving);
        $marking = DB::table('event_orbat_markings')
            ->where('event_id', $event->id)->where('participant_key', $participantKey)->first();
        if ($marking) {
            DB::table('event_orbat_markings')->where('event_id', $event->id)
                ->where('participant_key', 'vacant:' . $leaving->slot_key)->delete();
            DB::table('event_orbat_markings')->where('id', $marking->id)->update([
                'participant_key' => 'vacant:' . $leaving->slot_key,
                'color' => 'white',
                'updated_by' => $user->id,
                'updated_at' => now(),
            ]);
        }
        // A departing 2IC clears the designation, but leaves all other
        // members' numbering untouched.
        DB::table('event_orbat_group_settings')
            ->where('event_id', $event->id)
            ->where('second_in_command_key', $participantKey)
            ->update(['second_in_command_key' => null, 'updated_at' => now()]);
    }


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

    private function participantKey(EventSlot $slot): ?string
    {
        if ($slot->user_id !== null) return 'user:' . $slot->user_id;
        if ($slot->ally_id !== null) return 'ally-slot:' . $slot->id;
        return null;
    }

    /** @return array<string, array{number:?int,color:string}> Current slot key => player marking */
    public function markings(Event $event): array
    {
        $byPlayer = DB::table('event_orbat_markings')
            ->where('event_id', $event->id)->get()->keyBy('participant_key');
        $result = [];
        foreach (EventSlot::query()->where('event_id', $event->id)->get() as $slot) {
            $key = $this->participantKey($slot);
            $marking = $key ? $byPlayer->get($key) : null;
            if ($marking) {
                $result[$slot->slot_key] = [
                    'number' => $marking->number !== null ? (int) $marking->number : null,
                    'color' => $marking->color,
                ];
            }
        }
        // A departing member leaves a numbered vacancy (white, unnamed).
        // Numbers are not compacted and do not automatically transfer to a replacement.
        $vacancies = DB::table('event_orbat_markings')
            ->where('event_id', $event->id)
            ->where('participant_key', 'like', 'vacant:%')
            ->get();
        $occupiedKeys = EventSlot::query()->where('event_id', $event->id)->pluck('slot_key')->all();
        foreach ($vacancies as $vacancy) {
            if (! in_array($vacancy->slot_key, $occupiedKeys, true) && ! isset($result[$vacancy->slot_key])) {
                $result[$vacancy->slot_key] = [
                    'number' => $vacancy->number !== null ? (int) $vacancy->number : null,
                    'color' => 'white',
                ];
            }
        }
        return $result;
    }

    /** @return array<string, array{second_in_command_key:?string, updated_by:?string, updated_at:?string}> */
    public function groupSettings(Event $event): array
    {
        if (! Schema::hasTable('event_orbat_group_settings')) {
            return [];
        }

        $participantSlots = EventSlot::query()->where('event_id', $event->id)->get()
            ->filter(fn (EventSlot $slot): bool => $this->participantKey($slot) !== null)
            ->keyBy(fn (EventSlot $slot): string => $this->participantKey($slot));

        return DB::table('event_orbat_group_settings as g')
            ->leftJoin('users as editor', 'editor.id', '=', 'g.updated_by')
            ->where('g.event_id', $event->id)
            ->select('g.group_name', 'g.custom_title', 'g.second_in_command_key', 'g.updated_at', 'editor.nick as updated_by')
            ->get()->mapWithKeys(function ($row) use ($participantSlots): array {
                $second = $row->second_in_command_key
                    ? $participantSlots->get($row->second_in_command_key)
                    : null;
                return [$row->group_name => [
                    'custom_title' => $row->custom_title,
                    'second_in_command_key' => $second && $second->slot_group === $row->group_name
                        ? (string) $second->slot_key : null,
                    'updated_by' => $row->updated_by,
                    'updated_at' => $row->updated_at,
                ]];
            })->all();
    }

    /**
     * @param array<int, array{slot_key:string, number:?int, color:string}> $items
     * @return bool true only if something actually changed
     */
    public function save(Event $event, User $actor, string $group, array $items, ?string $secondInCommandKey = null, ?string $customTitle = null): bool
    {
        return DB::transaction(function () use ($event, $actor, $group, $items, $secondInCommandKey, $customTitle): bool {
            $event = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $event->load('eventStatus');
            $visible = $this->visibleSlots($event);
            if (! $this->permission($event, $actor, $group)) {
                abort(403, 'No tienes permiso para editar esta escuadra.');
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
                ->whereIn('slot_key', $groupKeys)->lockForUpdate()->get()->keyBy('slot_key');
            if ($secondInCommandKey !== null
                && (! in_array($secondInCommandKey, $groupKeys, true)
                    || ! $occupied->has($secondInCommandKey))) {
                throw ValidationException::withMessages([
                    'second_in_command' => 'El segundo al mando debe estar apuntado en esta escuadra.',
                ]);
            }

            $secondParticipantKey = $secondInCommandKey !== null
                ? $this->participantKey($occupied->get($secondInCommandKey))
                : null;

            $vacantMarkings = DB::table('event_orbat_markings')
                ->where('event_id', $event->id)
                ->where('participant_key', 'like', 'vacant:%')
                ->get()->keyBy('slot_key');

            $numbers = [];
            foreach ($items as $item) {
                if (! in_array($item['color'], self::COLORS, true)) {
                    throw ValidationException::withMessages(['markings' => 'Color no permitido.']);
                }
                if (! $occupied->has($item['slot_key'])) {
                    $vacancy = $vacantMarkings->get($item['slot_key']);
                    $expectedNumber = $vacancy?->number !== null ? (int) $vacancy->number : null;
                    if ($item['number'] !== $expectedNumber || $item['color'] !== 'white') {
                        throw ValidationException::withMessages([
                            'markings' => 'Los puestos vacíos conservan su número, pero no se pueden editar.',
                        ]);
                    }
                }
                if ($item['number'] !== null) {
                    if (in_array($item['number'], $numbers, true)) {
                        throw ValidationException::withMessages(['markings' => 'No se puede repetir un número en la misma escuadra.']);
                    }
                    $numbers[] = $item['number'];
                }
            }

            $existing = DB::table('event_orbat_markings')
                ->where('event_id', $event->id)->get()->keyBy('participant_key');
            $changed = false;
            foreach ($items as $item) {
                $slot = $occupied->get($item['slot_key']);
                if (! $slot) continue;
                $participantKey = $this->participantKey($slot);
                if (! $participantKey) continue;
                $old = $existing->get($participantKey);
                $oldNumber = $old?->number !== null ? (int) $old->number : null;
                $oldColor = $old?->color ?? 'white';
                if ($oldNumber === $item['number'] && $oldColor === $item['color']) continue;
                DB::table('event_orbat_marking_history')->insert([
                    'event_id' => $event->id,
                    'actor_id' => $actor->id,
                    'group_name' => $group,
                    'participant_key' => $participantKey,
                    'slot_key' => $item['slot_key'],
                    'old_number' => $oldNumber,
                    'new_number' => $item['number'],
                    'old_color' => $oldColor,
                    'new_color' => $item['color'],
                    'created_at' => now(),
                ]);
                DB::table('event_orbat_markings')->updateOrInsert([
                    'event_id' => $event->id, 'participant_key' => $participantKey,
                ], [
                    'slot_key' => $item['slot_key'],
                    'number' => $item['number'], 'color' => $item['color'],
                    'updated_by' => $actor->id, 'updated_at' => now(),
                    'created_at' => $old?->created_at ?? now(),
                ]);
                $changed = true;
            }
            // Persist squad leadership independently of the slot so it is optional,
            // and update editor metadata only after a real change.
            $previousSettings = DB::table('event_orbat_group_settings')
                ->where('event_id', $event->id)
                ->where('group_name', $group)
                ->first();
            $previousSecond = $previousSettings?->second_in_command_key;
            $previousTitle = trim((string) ($previousSettings?->custom_title ?? ''));
            $newTitle = trim((string) $customTitle);
            $secondChanged = $previousSecond !== $secondParticipantKey;
            $titleChanged = $previousTitle !== $newTitle;
            if ($secondChanged || $titleChanged) {
                DB::table('event_orbat_group_command_history')->insert([
                    'event_id' => $event->id,
                    'group_name' => $group,
                    'previous_title' => $previousTitle ?: null,
                    'new_title' => $newTitle ?: null,
                    'previous_second_key' => $previousSecond,
                    'new_second_key' => $secondParticipantKey,
                    'actor_id' => $actor->id,
                    'created_at' => now(),
                ]);
                $changed = true;
            }
            if ($changed) {
                DB::table('event_orbat_group_settings')->updateOrInsert(
                    ['event_id' => $event->id, 'group_name' => $group],
                    [
                        'custom_title' => $newTitle ?: null,
                        'second_in_command_key' => $secondParticipantKey,
                        'updated_by' => $actor->id,
                        'updated_at' => now(),
                        'created_at' => $previousSettings?->created_at ?? now(),
                    ],
                );
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
