<?php

namespace App\Services;

use App\Models\CommunityRouletteCandidate;
use App\Models\CommunityRoulettePhrase;
use App\Models\CommunityRoulettePreviousEvent;
use App\Models\CommunityRouletteRoom;
use App\Models\CommunityRouletteSlotTypeRule;
use App\Models\CommunityRouletteViewer;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\Faction;
use App\Models\SlotType;
use App\Models\User;
use App\Notifications\CommunityRouletteWinnerNotification;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class CommunityRouletteService
{
    public const ROOM_MINUTES = 30;
    public const SPIN_DURATION_MS = 9000;
    public const VIEWER_WINDOW_SECONDS = 12;

    public function canView(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        if ($user->hasRole('admin')) {
            return true;
        }

        $user->loadMissing('status');

        return in_array(
            strtoupper(trim((string) $user->status?->name)),
            ['ACTIVO', 'RECLUTA'],
            true,
        );
    }

    public function canManage(?User $user): bool
    {
        return $this->canView($user)
            && ($user?->hasRole('admin') || $user?->can('community-roulette.manage'));
    }

    public function canControlRoom(?User $user, CommunityRouletteRoom $room): bool
    {
        if (! $this->canManage($user)) {
            return false;
        }

        return $user?->hasRole('admin')
            || (int) $room->created_by === (int) $user?->id;
    }

    public function canRepeatRoom(?User $user, CommunityRouletteRoom $room): bool
    {
        return $this->canControlRoom($user, $room)
            && $room->status === CommunityRouletteRoom::STATUS_COMPLETED
            && ($room->expires_at?->isFuture() ?? false);
    }

    public function eligibleEvents(): Collection
    {
        return Event::query()
            ->whereHas('eventStatus', fn (Builder $query) => $query->whereRaw("UPPER(TRIM(name)) = 'ACTIVO'"))
            ->whereHas('activity.activityType', fn (Builder $query) => $this->operationTypeQuery($query))
            ->with([
                'activity.activityType',
                'eventStatus',
                'slots:user_id,event_id',
            ])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function previousEventOptions(Event $event): Collection
    {
        return Event::query()
            ->where('id', '!=', $event->id)
            ->where('date', '<', $event->date)
            ->whereHas('activity.activityType', fn (Builder $query) => $this->operationTypeQuery($query))
            ->whereHas('eventStatus', function (Builder $query): void {
                $query->whereRaw("UPPER(TRIM(name)) NOT IN ('CANCELADO', 'BORRADOR')");
            })
            ->with(['activity.activityType', 'eventStatus'])
            ->orderByDesc('date')
            ->orderByDesc('id')
            ->get();
    }

    public function defaultPreviousEventIds(Event $event): array
    {
        return $this->previousEventOptions($event)
            ->take(3)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function availableTargetSlots(Event $event): array
    {
        $event->loadMissing('eventStatus');

        if (strtoupper(trim((string) $event->eventStatus?->name)) !== 'ACTIVO') {
            return [];
        }

        $event->ensureOrbatSlotKeys();
        $event->refresh();

        $occupiedKeys = EventSlot::query()
            ->where('event_id', $event->id)
            ->where(fn (Builder $query) => $query
                ->whereNotNull('user_id')
                ->orWhereNotNull('ally_id'))
            ->pluck('slot_key')
            ->map(fn ($key): string => (string) $key)
            ->all();

        $slotTypeIds = collect($event->orbat['groups'] ?? [])
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_type_id')
            ->filter()
            ->unique();

        $slotTypes = SlotType::query()
            ->whereIn('id', $slotTypeIds)
            ->pluck('name', 'id');

        $slots = [];

        foreach (($event->orbat['groups'] ?? []) as $group) {
            if (! (bool) ($group['visible'] ?? true)) {
                continue;
            }

            foreach (($group['slots'] ?? []) as $slot) {
                if (! (bool) ($slot['visible'] ?? true)) {
                    continue;
                }

                $slotKey = trim((string) ($slot['slot_key'] ?? ''));
                $slotTypeId = (int) ($slot['slot_type_id'] ?? 0);
                $factionId = (int) ($group['faction_id'] ?? 0);

                if (
                    $slotKey === ''
                    || $slotTypeId < 1
                    || $factionId < 1
                    || in_array($slotKey, $occupiedKeys, true)
                ) {
                    continue;
                }

                $slotName = (string) ($slot['name'] ?? 'Slot sin nombre');
                $groupName = (string) ($group['name'] ?? 'Grupo sin nombre');
                $slotTypeName = (string) ($slotTypes[$slotTypeId] ?? 'Sin tipo');

                $slots[] = [
                    'key' => $slotKey,
                    'name' => $slotName,
                    'group' => $groupName,
                    'slot_type_id' => $slotTypeId,
                    'slot_type_name' => $slotTypeName,
                    'faction_id' => $factionId,
                    'label' => "{$groupName} · {$slotName} · {$slotTypeName}",
                ];
            }
        }

        return $slots;
    }

    public function createRoom(
        User $creator,
        Event $event,
        string $targetSlotKey,
        array $previousEventIds,
    ): CommunityRouletteRoom {
        $this->refreshDueRooms();

        if (! $this->canManage($creator)) {
            abort(403);
        }

        $event->loadMissing(['eventStatus', 'activity.activityType']);

        if (
            strtoupper(trim((string) $event->eventStatus?->name)) !== 'ACTIVO'
            || ! $this->isOperationEvent($event)
        ) {
            throw ValidationException::withMessages([
                'event_id' => 'La ruleta solo puede crearse para un evento ACTIVO de una actividad tipo OPERACIÓN.',
            ]);
        }

        $event->ensureOrbatSlotKeys();
        $event->refresh();
        $target = $this->findVisibleOrbatSlot($event, $targetSlotKey);

        if (! $target) {
            throw ValidationException::withMessages([
                'target_slot_key' => 'El slot que quieres sortear ya no existe o no está visible.',
            ]);
        }

        if (
            (int) ($target['slot']['slot_type_id'] ?? 0) < 1
            || (int) ($target['group']['faction_id'] ?? 0) < 1
        ) {
            throw ValidationException::withMessages([
                'target_slot_key' => 'El slot que quieres sortear no tiene un tipo de slot o una facción válidos.',
            ]);
        }

        $occupied = EventSlot::query()
            ->where('event_id', $event->id)
            ->where('slot_key', $targetSlotKey)
            ->where(fn (Builder $query) => $query
                ->whereNotNull('user_id')
                ->orWhereNotNull('ally_id'))
            ->exists();

        if ($occupied) {
            throw ValidationException::withMessages([
                'target_slot_key' => 'El slot que quieres sortear ya está ocupado.',
            ]);
        }

        $previousEventIds = $this->validatePreviousEventIds($event, $previousEventIds);

        try {
            return DB::transaction(function () use ($creator, $event, $targetSlotKey, $previousEventIds): CommunityRouletteRoom {
            /*
             * Orden global de locks: sala -> evento. Es el mismo orden usado
             * al finalizar una tirada, evitando un deadlock si justo coincide
             * la creación con el cierre/finalización de la sala anterior.
             */
            $existing = CommunityRouletteRoom::query()
                ->where('active_key', 1)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                throw ValidationException::withMessages([
                    'room' => 'Ya existe una ruleta activa. Debe terminarse o cerrarse antes de crear otra.',
                ]);
            }

            // El mismo bloqueo de fila se utiliza en las altas/bajas/movimientos
            // del ORBAT. Así no queda una ventana de carrera entre congelar la
            // partida y que otro usuario consiga apuntarse en ese instante.
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedEvent->loadMissing(['eventStatus', 'activity.activityType']);

            if (
                strtoupper(trim((string) $lockedEvent->eventStatus?->name)) !== 'ACTIVO'
                || ! $this->isOperationEvent($lockedEvent)
            ) {
                throw ValidationException::withMessages([
                    'event_id' => 'El evento ha dejado de estar disponible para la ruleta.',
                ]);
            }

            $target = $this->findVisibleOrbatSlot($lockedEvent, $targetSlotKey);

            if (! $target) {
                throw ValidationException::withMessages([
                    'target_slot_key' => 'El slot que quieres sortear ya no existe o no está visible.',
                ]);
            }

            if (
                (int) ($target['slot']['slot_type_id'] ?? 0) < 1
                || (int) ($target['group']['faction_id'] ?? 0) < 1
            ) {
                throw ValidationException::withMessages([
                    'target_slot_key' => 'El slot que quieres sortear ha quedado con una configuración inválida.',
                ]);
            }

            $occupied = EventSlot::query()
                ->where('event_id', $lockedEvent->id)
                ->where('slot_key', $targetSlotKey)
                ->where(fn (Builder $query) => $query
                    ->whereNotNull('user_id')
                    ->orWhereNotNull('ally_id'))
                ->lockForUpdate()
                ->exists();

            if ($occupied) {
                throw ValidationException::withMessages([
                    'target_slot_key' => 'El slot que quieres sortear acaba de ser ocupado.',
                ]);
            }

            $room = CommunityRouletteRoom::query()->create([
                'event_id' => $lockedEvent->id,
                'target_slot_key' => $target['slot']['slot_key'],
                'target_slot_name' => $target['slot']['name'] ?? 'Slot sin nombre',
                'target_slot_type_id' => (int) ($target['slot']['slot_type_id'] ?? 0),
                'target_slot_group' => $target['group']['name'] ?? 'Grupo sin nombre',
                'target_faction_id' => (int) ($target['group']['faction_id'] ?? 0),
                'created_by' => $creator->id,
                'status' => CommunityRouletteRoom::STATUS_ACTIVE,
                'active_key' => 1,
                'expires_at' => now()->addMinutes(self::ROOM_MINUTES),
            ]);

            $this->syncPreviousEvents($room, $previousEventIds);
            $this->syncRules($room);
            $this->recalculateCandidates($room);

                return $room->fresh($this->roomRelations());
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'community_roulette_rooms_active_key_unique')) {
                throw ValidationException::withMessages([
                    'room' => 'Otra persona acaba de crear una ruleta. Solo puede existir una sala activa.',
                ]);
            }

            throw $exception;
        }
    }

    public function updateConfiguration(
        CommunityRouletteRoom $room,
        User $user,
        array $previousEventIds,
        array $responsibilitySlotTypeIds,
    ): CommunityRouletteRoom {
        $room = $this->refreshRoomLifecycle($room);
        $this->assertConfigurable($room, $user);

        $event = $room->event()->with(['activity.activityType'])->firstOrFail();
        $previousEventIds = $this->validatePreviousEventIds($event, $previousEventIds);
        $responsibilitySlotTypeIds = array_values(array_unique(array_map('intval', $responsibilitySlotTypeIds)));

        return DB::transaction(function () use ($room, $previousEventIds, $responsibilitySlotTypeIds): CommunityRouletteRoom {
            $locked = CommunityRouletteRoom::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeConfigured()) {
                throw ValidationException::withMessages([
                    'room' => 'La sala ya no puede modificarse.',
                ]);
            }

            $this->syncPreviousEvents($locked, $previousEventIds);
            $this->syncRules($locked, $responsibilitySlotTypeIds);
            $this->recalculateCandidates($locked);

            return $locked->fresh($this->roomRelations());
        });
    }

    public function startSpin(CommunityRouletteRoom $room, User $user): CommunityRouletteRoom
    {
        $room = $this->refreshRoomLifecycle($room);
        $this->assertConfigurable($room, $user);

        return DB::transaction(function () use ($room): CommunityRouletteRoom {
            /*
             * La sala se bloquea antes de recalcular. Así dos clics casi
             * simultáneos sobre GIRAR no pueden borrar/recrear candidatos
             * mientras la otra petición ya está resolviendo el ganador.
             */
            $locked = CommunityRouletteRoom::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->canBeConfigured()) {
                throw ValidationException::withMessages([
                    'room' => 'La sala ya no está preparada para girar.',
                ]);
            }

            $event = Event::query()
                ->whereKey($locked->event_id)
                ->with('eventStatus')
                ->lockForUpdate()
                ->firstOrFail();

            if (strtoupper(trim((string) $event->eventStatus?->name)) !== 'ACTIVO') {
                throw ValidationException::withMessages([
                    'room' => 'El evento ya no está ACTIVO.',
                ]);
            }

            $targetSnapshot = $this->findVisibleOrbatSlot($event, $locked->target_slot_key);
            if (! $targetSnapshot) {
                throw ValidationException::withMessages([
                    'room' => 'El slot sorteado ya no existe o ha dejado de estar visible en el ORBAT.',
                ]);
            }

            if (! $this->targetSnapshotMatchesRoom($locked, $targetSnapshot)) {
                throw ValidationException::withMessages([
                    'room' => 'La configuración del slot sorteado cambió desde que se creó la sala. Cierra la ruleta y créala de nuevo para evitar asignaciones incorrectas.',
                ]);
            }

            $targetSlot = EventSlot::query()
                ->where('event_id', $locked->event_id)
                ->where('slot_key', $locked->target_slot_key)
                ->lockForUpdate()
                ->first();

            if ($targetSlot && ($targetSlot->user_id || $targetSlot->ally_id)) {
                throw ValidationException::withMessages([
                    'room' => 'El slot sorteado ha sido ocupado. Cierra la sala y revisa el ORBAT.',
                ]);
            }

            /*
             * Las papeletas son una fotografía: se calculan al crear la sala y
             * solo cambian cuando el creador pulsa explícitamente "Recalcular"
             * después de editar históricos/responsabilidades. GIRAR utiliza
             * exactamente los números que todos han estado viendo.
             */
            $candidates = CommunityRouletteCandidate::query()
                ->where('room_id', $locked->id)
                ->where('tickets', '>', 0)
                ->orderBy('nick_snapshot')
                ->lockForUpdate()
                ->get();

            $totalTickets = (int) $candidates->sum('tickets');

            if ($totalTickets < 1) {
                throw ValidationException::withMessages([
                    'room' => 'No hay ninguna papeleta válida para realizar el sorteo.',
                ]);
            }

            $ticketIndex = random_int(0, $totalTickets - 1);
            $cursor = 0;
            $winner = null;

            foreach ($candidates as $candidate) {
                $cursor += (int) $candidate->tickets;
                if ($ticketIndex < $cursor) {
                    $winner = $candidate;
                    break;
                }
            }

            if (! $winner?->user_id) {
                throw ValidationException::withMessages([
                    'room' => 'No se ha podido resolver un ganador válido.',
                ]);
            }

            $phrase = CommunityRoulettePhrase::query()
                ->where('active', true)
                ->inRandomOrder()
                ->first();

            $phraseText = $phrase?->text
                ?: 'La ruleta ha hablado. Nuestras condolencias.';

            $spinStartsAt = now()->addSecond();
            $spinEndsAt = $spinStartsAt->copy()->addMilliseconds(self::SPIN_DURATION_MS);
            $turns = random_int(6, 8);
            $ticketCenterDegrees = (($ticketIndex + 0.5) / $totalTickets) * 360;
            $landingDegrees = fmod(360 - $ticketCenterDegrees, 360);
            $finalRotation = ($turns * 360) + $landingDegrees;
            $minimumExpiry = $spinEndsAt->copy()->addMinute();

            $locked->forceFill([
                'status' => CommunityRouletteRoom::STATUS_SPINNING,
                'spin_started_at' => $spinStartsAt,
                'spin_ends_at' => $spinEndsAt,
                'spin_duration_ms' => self::SPIN_DURATION_MS,
                'winning_ticket_index' => $ticketIndex,
                'final_rotation' => round($finalRotation, 3),
                'winner_user_id' => $winner->user_id,
                'winner_phrase_id' => $phrase?->id,
                'winner_phrase_text' => $phraseText,
                'expires_at' => $locked->expires_at && $locked->expires_at->greaterThan($minimumExpiry)
                    ? $locked->expires_at
                    : $minimumExpiry,
            ])->save();

            return $locked->fresh($this->roomRelations());
        });
    }

    public function repeatRoom(CommunityRouletteRoom $room, User $user): CommunityRouletteRoom
    {
        $room = $this->refreshRoomLifecycle($room);

        if (! $this->canControlRoom($user, $room)) {
            abort(403);
        }

        try {
            return DB::transaction(function () use ($room): CommunityRouletteRoom {
                $locked = CommunityRouletteRoom::query()
                    ->whereKey($room->id)
                    ->lockForUpdate()
                    ->firstOrFail();

                if (
                    $locked->status !== CommunityRouletteRoom::STATUS_COMPLETED
                    || ! ($locked->expires_at?->isFuture() ?? false)
                ) {
                    throw ValidationException::withMessages([
                        'room' => 'Esta ruleta ya no puede repetirse. Crea una nueva sala si necesitas otro sorteo.',
                    ]);
                }

                $otherActiveRoom = CommunityRouletteRoom::query()
                    ->where('active_key', 1)
                    ->where('id', '!=', $locked->id)
                    ->lockForUpdate()
                    ->first();

                if ($otherActiveRoom) {
                    throw ValidationException::withMessages([
                        'room' => 'Ya existe otra ruleta activa. Debe terminarse o cerrarse antes de repetir esta.',
                    ]);
                }

                $event = Event::query()
                    ->whereKey($locked->event_id)
                    ->with('eventStatus')
                    ->lockForUpdate()
                    ->firstOrFail();

                if (strtoupper(trim((string) $event->eventStatus?->name)) !== 'ACTIVO') {
                    throw ValidationException::withMessages([
                        'room' => 'El evento ya no está ACTIVO y no se puede repetir la ruleta.',
                    ]);
                }

                $targetSnapshot = $this->findVisibleOrbatSlot($event, $locked->target_slot_key);
                if (! $targetSnapshot || ! $this->targetSnapshotMatchesRoom($locked, $targetSnapshot)) {
                    throw ValidationException::withMessages([
                        'room' => 'El slot sorteado ha cambiado desde la primera tirada. Crea una nueva sala para evitar mover jugadores a un slot incorrecto.',
                    ]);
                }

                $previousWinnerId = (int) $locked->winner_user_id;
                if ($previousWinnerId < 1) {
                    throw ValidationException::withMessages([
                        'room' => 'La sala no conserva un ganador anterior válido para repetir el sorteo.',
                    ]);
                }

                $candidate = CommunityRouletteCandidate::query()
                    ->where('room_id', $locked->id)
                    ->where('user_id', $previousWinnerId)
                    ->lockForUpdate()
                    ->first();

                if (! $candidate) {
                    throw ValidationException::withMessages([
                        'room' => 'No se encuentra la papeleta histórica del ganador anterior.',
                    ]);
                }

                $targetSlot = EventSlot::query()
                    ->where('event_id', $event->id)
                    ->where('slot_key', $locked->target_slot_key)
                    ->with('faction')
                    ->lockForUpdate()
                    ->first();

                if (
                    $targetSlot
                    && ($targetSlot->ally_id || ($targetSlot->user_id && (int) $targetSlot->user_id !== $previousWinnerId))
                ) {
                    throw ValidationException::withMessages([
                        'room' => 'El slot sorteado está ocupado ahora por otra persona. Libéralo antes de repetir la ruleta.',
                    ]);
                }

                /*
                 * El ganador anterior queda descartado de las siguientes
                 * tiradas de esta misma sala. Si todavía está en el slot que
                 * ganó, intentamos devolverlo a su posición original. Si esa
                 * posición ya está ocupada, queda sin slot: es preferible a
                 * desplazar a un tercero mientras el ORBAT vuelve a bloquearse.
                 */
                if ($targetSlot && (int) $targetSlot->user_id === $previousWinnerId) {
                    $originalSnapshot = $candidate->current_slot_key
                        ? $this->findVisibleOrbatSlot($event, $candidate->current_slot_key)
                        : null;

                    $restored = false;
                    if ($originalSnapshot && $candidate->current_slot_key !== $locked->target_slot_key) {
                        $originalSlot = EventSlot::query()
                            ->where('event_id', $event->id)
                            ->where('slot_key', $candidate->current_slot_key)
                            ->with('faction')
                            ->lockForUpdate()
                            ->first();

                        $originalOccupied = $originalSlot
                            && ($originalSlot->user_id || $originalSlot->ally_id);

                        if (! $originalOccupied) {
                            $originalFactionId = (int) ($originalSnapshot['group']['faction_id'] ?? 0);
                            $originalArmyId = Faction::query()->whereKey($originalFactionId)->value('army_id');
                            $from = [
                                'slot_key' => $targetSlot->slot_key,
                                'name' => $targetSlot->name,
                                'slot_type_id' => $targetSlot->slot_type_id,
                                'slot_group' => $targetSlot->slot_group,
                                'army_id' => $targetSlot->faction?->army_id,
                            ];
                            $to = [
                                'slot_key' => (string) $candidate->current_slot_key,
                                'name' => (string) ($originalSnapshot['slot']['name'] ?? $candidate->current_slot_name ?? 'Slot'),
                                'slot_type_id' => (int) ($originalSnapshot['slot']['slot_type_id'] ?? $candidate->current_slot_type_id),
                                'slot_group' => (string) ($originalSnapshot['group']['name'] ?? ''),
                                'faction_id' => $originalFactionId,
                                'army_id' => $originalArmyId,
                            ];

                            if ($originalSlot) {
                                $targetSlot->forceFill([
                                    'user_id' => null,
                                    'ally_id' => null,
                                ])->save();

                                $originalSlot->forceFill([
                                    'name' => $to['name'],
                                    'slot_type_id' => $to['slot_type_id'],
                                    'slot_group' => $to['slot_group'],
                                    'faction_id' => $to['faction_id'],
                                    'user_id' => $previousWinnerId,
                                    'ally_id' => null,
                                ])->save();

                                $historySlot = $originalSlot;
                            } else {
                                $targetSlot->forceFill([
                                    'slot_key' => $to['slot_key'],
                                    'name' => $to['name'],
                                    'slot_type_id' => $to['slot_type_id'],
                                    'slot_group' => $to['slot_group'],
                                    'faction_id' => $to['faction_id'],
                                    'user_id' => $previousWinnerId,
                                    'ally_id' => null,
                                ])->save();

                                $historySlot = $targetSlot;
                            }

                            EventSlotHistory::query()->create([
                                'event_slot_id' => $historySlot->id,
                                'event_id' => $event->id,
                                'user_id' => $previousWinnerId,
                                'ally_id' => null,
                                'action' => 'moved',
                                'from_slot_key' => $from['slot_key'],
                                'from_slot_name' => $from['name'],
                                'from_slot_type_id' => $from['slot_type_id'],
                                'from_slot_group' => $from['slot_group'],
                                'from_army_id' => $from['army_id'],
                                'to_slot_key' => $to['slot_key'],
                                'to_slot_name' => $to['name'],
                                'to_slot_type_id' => $to['slot_type_id'],
                                'to_slot_group' => $to['slot_group'],
                                'to_army_id' => $to['army_id'],
                                'changed_by_user_id' => $locked->created_by,
                                'created_at' => now(),
                            ]);

                            $restored = true;
                        }
                    }

                    if (! $restored) {
                        EventSlotHistory::query()->create([
                            'event_slot_id' => $targetSlot->id,
                            'event_id' => $event->id,
                            'user_id' => $previousWinnerId,
                            'ally_id' => null,
                            'action' => 'unassigned',
                            'from_slot_key' => $targetSlot->slot_key,
                            'from_slot_name' => $targetSlot->name,
                            'from_slot_type_id' => $targetSlot->slot_type_id,
                            'from_slot_group' => $targetSlot->slot_group,
                            'from_army_id' => $targetSlot->faction?->army_id,
                            'to_slot_key' => null,
                            'to_slot_name' => null,
                            'to_slot_type_id' => null,
                            'to_slot_group' => null,
                            'to_army_id' => null,
                            'changed_by_user_id' => $locked->created_by,
                            'created_at' => now(),
                        ]);

                        $targetSlot->delete();
                    }
                }

                $details = $candidate->details ?? [];
                $exclusions = array_values(array_unique(array_filter([
                    ...($details['exclusions'] ?? []),
                    'Ganador anterior descartado para repetir el sorteo',
                ])));
                $candidate->forceFill([
                    'tickets' => 0,
                    'excluded_reason' => implode(' · ', $exclusions),
                    'details' => [
                        ...$details,
                        'exclusions' => $exclusions,
                    ],
                    'is_winner' => true,
                ])->save();

                $locked->forceFill([
                    'status' => CommunityRouletteRoom::STATUS_ACTIVE,
                    'active_key' => 1,
                    'spin_started_at' => null,
                    'spin_ends_at' => null,
                    'spin_duration_ms' => null,
                    'winning_ticket_index' => null,
                    'final_rotation' => null,
                    'winner_user_id' => null,
                    'winner_was_viewing' => false,
                    'winner_phrase_id' => null,
                    'winner_phrase_text' => null,
                    'failure_reason' => null,
                    'completed_at' => null,
                    'closed_at' => null,
                ])->save();

                return $locked->fresh($this->roomRelations());
            });
        } catch (UniqueConstraintViolationException $exception) {
            if (str_contains($exception->getMessage(), 'community_roulette_rooms_active_key_unique')) {
                throw ValidationException::withMessages([
                    'room' => 'Otra persona acaba de activar una ruleta. Solo puede existir una sala activa.',
                ]);
            }

            throw $exception;
        }
    }

    public function closeRoom(CommunityRouletteRoom $room, User $user): CommunityRouletteRoom
    {
        $room = $this->refreshRoomLifecycle($room);

        if (! $this->canControlRoom($user, $room)) {
            abort(403);
        }

        return DB::transaction(function () use ($room): CommunityRouletteRoom {
            $locked = CommunityRouletteRoom::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->locksEventRegistration()) {
                return $locked->fresh($this->roomRelations());
            }

            if ($locked->status === CommunityRouletteRoom::STATUS_SPINNING) {
                throw ValidationException::withMessages([
                    'room' => 'La ruleta ya está girando. La tirada debe terminar para mantener el mismo resultado para todos los espectadores.',
                ]);
            }

            $locked->forceFill([
                'status' => CommunityRouletteRoom::STATUS_CLOSED,
                'active_key' => null,
                'closed_at' => now(),
            ])->save();

            return $locked->fresh($this->roomRelations());
        });
    }

    public function heartbeat(CommunityRouletteRoom $room, User $user): void
    {
        CommunityRouletteViewer::query()->updateOrCreate(
            [
                'room_id' => $room->id,
                'user_id' => $user->id,
            ],
            [
                'last_seen_at' => now(),
            ],
        );
    }

    public function state(CommunityRouletteRoom $room, User $user): array
    {
        $room = $this->refreshRoomLifecycle($room);
        $room->loadMissing($this->roomRelations());
        $this->heartbeat($room, $user);

        $viewerCutoff = now()->subSeconds(self::VIEWER_WINDOW_SECONDS);
        $viewers = CommunityRouletteViewer::query()
            ->where('room_id', $room->id)
            ->where('last_seen_at', '>=', $viewerCutoff)
            ->with('user:id,nick')
            ->orderByDesc('last_seen_at')
            ->get()
            ->map(fn (CommunityRouletteViewer $viewer): array => [
                'id' => (int) $viewer->user_id,
                'nick' => $viewer->user?->nick ?: 'Usuario',
            ])
            ->values();

        $wheelCandidates = $room->candidates
            ->where('tickets', '>', 0)
            ->values();

        $completed = $room->status === CommunityRouletteRoom::STATUS_COMPLETED;
        $canControl = $this->canControlRoom($user, $room);

        return [
            'room_id' => (int) $room->id,
            'status' => $room->status,
            'locks_event' => $room->locksEventRegistration(),
            'can_control' => $canControl,
            'can_configure' => $canControl && $room->canBeConfigured(),
            'can_repeat' => $this->canRepeatRoom($user, $room),
            'expires_at' => $room->expires_at?->toIso8601String(),
            'spin_started_at' => $room->spin_started_at?->toIso8601String(),
            'spin_ends_at' => $room->spin_ends_at?->toIso8601String(),
            'spin_duration_ms' => $room->spin_duration_ms,
            'final_rotation' => $room->final_rotation,
            'server_time' => now()->toIso8601String(),
            'event' => [
                'id' => (int) $room->event_id,
                'name' => $room->event?->name ?: $room->event?->activity?->name ?: 'Evento',
                'url' => route('events.show', $room->event_id),
            ],
            'target_slot' => [
                'key' => $room->target_slot_key,
                'name' => $room->target_slot_name,
                'group' => $room->target_slot_group,
                'type' => $room->targetSlotType?->name,
            ],
            'viewers' => $viewers,
            'wheel' => $wheelCandidates->map(fn (CommunityRouletteCandidate $candidate): array => [
                'user_id' => (int) $candidate->user_id,
                'nick' => $candidate->nick_snapshot,
                'tickets' => (int) $candidate->tickets,
            ])->all(),
            'celebrate_winner' => $completed
                && $room->winner_was_viewing
                && (int) $room->winner_user_id === (int) $user->id
                && $room->completed_at?->gte(now()->subMinutes(3)),
            'winner' => $completed ? [
                'user_id' => (int) $room->winner_user_id,
                'nick' => $room->winner?->nick
                    ?: $room->candidates->firstWhere('user_id', $room->winner_user_id)?->nick_snapshot
                    ?: 'Ganador',
                'phrase' => $room->winner_phrase_text,
                'is_me' => (int) $room->winner_user_id === (int) $user->id,
            ] : null,
            'failure_reason' => $room->failure_reason,
        ];
    }

    public function refreshDueRooms(): void
    {
        CommunityRouletteRoom::query()
            ->where('active_key', 1)
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $query): void {
                        $query->where('status', CommunityRouletteRoom::STATUS_ACTIVE)
                            ->where('expires_at', '<=', now());
                    })
                    ->orWhere(function (Builder $query): void {
                        $query->where('status', CommunityRouletteRoom::STATUS_SPINNING)
                            ->whereNotNull('spin_ends_at')
                            ->where('spin_ends_at', '<=', now());
                    });
            })
            ->pluck('id')
            ->each(function ($roomId): void {
                $room = CommunityRouletteRoom::query()->find($roomId);
                if ($room) {
                    $this->refreshRoomLifecycle($room);
                }
            });
    }

    public function refreshRoomLifecycle(CommunityRouletteRoom $room): CommunityRouletteRoom
    {
        $needsTransition = (
            $room->status === CommunityRouletteRoom::STATUS_SPINNING
            && $room->spin_ends_at
            && $room->spin_ends_at->lte(now())
        ) || (
            $room->status === CommunityRouletteRoom::STATUS_ACTIVE
            && $room->expires_at
            && $room->expires_at->lte(now())
        );

        if (! $needsTransition) {
            return $room;
        }

        return DB::transaction(function () use ($room): CommunityRouletteRoom {
            $locked = CommunityRouletteRoom::query()
                ->whereKey($room->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (
                $locked->status === CommunityRouletteRoom::STATUS_SPINNING
                && $locked->spin_ends_at
                && $locked->spin_ends_at->lte(now())
            ) {
                $this->finalizeSpinLocked($locked);
            } elseif (
                $locked->status === CommunityRouletteRoom::STATUS_ACTIVE
                && $locked->expires_at
                && $locked->expires_at->lte(now())
            ) {
                $locked->forceFill([
                    'status' => CommunityRouletteRoom::STATUS_EXPIRED,
                    'active_key' => null,
                    'closed_at' => now(),
                ])->save();
            }

            return $locked->fresh($this->roomRelations());
        });
    }

    public function activeRoomForEvent(Event|int $event): ?CommunityRouletteRoom
    {
        $this->refreshDueRooms();
        $eventId = $event instanceof Event ? $event->id : $event;

        return CommunityRouletteRoom::query()
            ->where('event_id', $eventId)
            ->where('active_key', 1)
            ->whereIn('status', [
                CommunityRouletteRoom::STATUS_ACTIVE,
                CommunityRouletteRoom::STATUS_SPINNING,
            ])
            ->with(['creator', 'event.activity'])
            ->first();
    }

    public function currentDisplayRoom(): ?CommunityRouletteRoom
    {
        $this->refreshDueRooms();

        $active = CommunityRouletteRoom::query()
            ->where('active_key', 1)
            ->with($this->roomRelations())
            ->latest('id')
            ->first();

        if ($active) {
            return $active;
        }

        return CommunityRouletteRoom::query()
            ->where('status', CommunityRouletteRoom::STATUS_COMPLETED)
            ->where('expires_at', '>', now())
            ->with($this->roomRelations())
            ->latest('completed_at')
            ->first();
    }

    public function historyQuery(): Builder
    {
        $this->refreshDueRooms();

        return CommunityRouletteRoom::query()
            ->where(function (Builder $query): void {
                $query->whereNull('active_key')
                    ->orWhere('expires_at', '<=', now());
            })
            ->with(['event.activity', 'creator', 'winner', 'targetSlotType'])
            ->latest('created_at')
            ->latest('id');
    }

    public function assertEventUnlocked(Event $event): void
    {
        /*
         * Aquí no ejecutamos transiciones de ciclo de vida. Este método se
         * invoca también cuando la fila de events ya está bloqueada por FOR
         * UPDATE; intentar finalizar una ruleta desde ese punto invertiría el
         * orden de locks (evento -> sala frente a sala -> evento). Basta con
         * comprobar active_key: si acaba de vencer, la siguiente consulta de
         * estado/página la cerrará y, hasta entonces, bloquear unos segundos
         * adicionales es más seguro que abrir una carrera.
         */
        $locked = CommunityRouletteRoom::query()
            ->where('event_id', $event->id)
            ->where('active_key', 1)
            ->whereIn('status', [
                CommunityRouletteRoom::STATUS_ACTIVE,
                CommunityRouletteRoom::STATUS_SPINNING,
            ])
            ->exists();

        if ($locked) {
            throw ValidationException::withMessages([
                'slot' => 'Ruleta en juego: las inscripciones y movimientos del ORBAT están pausados hasta que termine o se cierre la sala.',
            ]);
        }
    }

    private function finalizeSpinLocked(CommunityRouletteRoom $room): void
    {
        if ($room->status !== CommunityRouletteRoom::STATUS_SPINNING || ! $room->winner_user_id) {
            return;
        }

        /*
         * La transición puede ser disparada por el polling de cualquier
         * espectador (o incluso por la comprobación pública del evento). Para
         * que updated_by y activity_log no atribuyan el movimiento a quien
         * simplemente tenía la pestaña abierta, ejecutamos la finalización con
         * el creador de la sala como actor y restauramos inmediatamente el
         * usuario original de la petición.
         */
        $guard = Auth::guard();
        $originalUser = $guard->user();
        $roomActor = $room->created_by
            ? User::withTrashed()->find($room->created_by)
            : null;

        if ($roomActor) {
            $guard->setUser($roomActor);
        }

        try {
            $event = Event::query()
                ->whereKey($room->event_id)
                ->with('eventStatus')
                ->lockForUpdate()
                ->first();

            if (! $event || strtoupper(trim((string) $event->eventStatus?->name)) !== 'ACTIVO') {
                $this->failLocked($room, 'El evento dejó de estar ACTIVO antes de terminar la tirada.');
                return;
            }

            $targetSnapshot = $this->findVisibleOrbatSlot($event, $room->target_slot_key);
            if (! $targetSnapshot) {
                $this->failLocked($room, 'El slot sorteado ya no existe en el ORBAT.');
                return;
            }

            if (! $this->targetSnapshotMatchesRoom($room, $targetSnapshot)) {
                $this->failLocked($room, 'La configuración del slot sorteado cambió durante la tirada. No se ha movido a ningún jugador.');
                return;
            }

            $winner = User::withTrashed()->find($room->winner_user_id);
            if (! $winner) {
                $this->failLocked($room, 'El usuario ganador ya no existe.');
                return;
            }

            $targetSlot = EventSlot::query()
                ->where('event_id', $event->id)
                ->where('slot_key', $room->target_slot_key)
                ->with('faction')
                ->lockForUpdate()
                ->first();

            $currentSlot = EventSlot::query()
                ->where('event_id', $event->id)
                ->where('user_id', $winner->id)
                ->with('faction')
                ->lockForUpdate()
                ->first();

            if ($targetSlot && (int) $targetSlot->user_id === (int) $winner->id) {
                $this->completeLocked($room, $winner);
                return;
            }

            if ($targetSlot && ($targetSlot->user_id || $targetSlot->ally_id)) {
                $this->failLocked($room, 'El slot sorteado fue ocupado durante la tirada.');
                return;
            }

            if (! $currentSlot) {
                $this->failLocked($room, 'El ganador ya no está asignado al ORBAT de este evento.');
                return;
            }

            $from = [
                'slot_key' => $currentSlot->slot_key,
                'name' => $currentSlot->name,
                'slot_type_id' => $currentSlot->slot_type_id,
                'slot_group' => $currentSlot->slot_group,
                'army_id' => $currentSlot->faction?->army_id,
            ];

            $targetFactionId = (int) ($targetSnapshot['group']['faction_id'] ?? 0);
            $targetArmyId = Faction::query()->whereKey($targetFactionId)->value('army_id');
            $to = [
                'slot_key' => $room->target_slot_key,
                'name' => $room->target_slot_name,
                'slot_type_id' => $room->target_slot_type_id,
                'slot_group' => $room->target_slot_group,
                'faction_id' => $targetFactionId,
                'army_id' => $targetArmyId,
            ];

            if ($targetSlot) {
                $currentSlot->forceFill([
                    'user_id' => null,
                    'ally_id' => null,
                ])->save();

                $targetSlot->forceFill([
                    'name' => $to['name'],
                    'slot_type_id' => $to['slot_type_id'],
                    'slot_group' => $to['slot_group'],
                    'faction_id' => $to['faction_id'],
                    'user_id' => $winner->id,
                    'ally_id' => null,
                ])->save();

                $eventSlot = $targetSlot;
            } else {
                $currentSlot->forceFill([
                    'slot_key' => $to['slot_key'],
                    'name' => $to['name'],
                    'slot_type_id' => $to['slot_type_id'],
                    'slot_group' => $to['slot_group'],
                    'faction_id' => $to['faction_id'],
                    'user_id' => $winner->id,
                    'ally_id' => null,
                ])->save();

                $eventSlot = $currentSlot;
            }

            EventSlotHistory::query()->create([
                'event_slot_id' => $eventSlot->id,
                'event_id' => $event->id,
                'user_id' => $winner->id,
                'ally_id' => null,
                'action' => 'moved',
                'from_slot_key' => $from['slot_key'],
                'from_slot_name' => $from['name'],
                'from_slot_type_id' => $from['slot_type_id'],
                'from_slot_group' => $from['slot_group'],
                'from_army_id' => $from['army_id'],
                'to_slot_key' => $to['slot_key'],
                'to_slot_name' => $to['name'],
                'to_slot_type_id' => $to['slot_type_id'],
                'to_slot_group' => $to['slot_group'],
                'to_army_id' => $to['army_id'],
                'changed_by_user_id' => $room->created_by,
                'created_at' => now(),
            ]);

            $this->completeLocked($room, $winner);
        } finally {
            if ($originalUser) {
                $guard->setUser($originalUser);
            } else {
                $guard->forgetUser();
            }
        }
    }

    private function completeLocked(CommunityRouletteRoom $room, User $winner): void
    {
        $winnerWasViewing = CommunityRouletteViewer::query()
            ->where('room_id', $room->id)
            ->where('user_id', $winner->id)
            ->where('last_seen_at', '>=', now()->subSeconds(self::VIEWER_WINDOW_SECONDS + 3))
            ->exists();

        CommunityRouletteCandidate::query()
            ->where('room_id', $room->id)
            ->where('user_id', $winner->id)
            ->update(['is_winner' => true]);

        $room->forceFill([
            'status' => CommunityRouletteRoom::STATUS_COMPLETED,
            'active_key' => null,
            'winner_was_viewing' => $winnerWasViewing,
            'completed_at' => now(),
            'failure_reason' => null,
        ])->save();

        $winner->notify(new CommunityRouletteWinnerNotification($room));
    }

    private function failLocked(CommunityRouletteRoom $room, string $reason): void
    {
        $room->forceFill([
            'status' => CommunityRouletteRoom::STATUS_FAILED,
            'active_key' => null,
            'failure_reason' => $reason,
            'closed_at' => now(),
        ])->save();
    }

    private function syncPreviousEvents(CommunityRouletteRoom $room, array $eventIds): void
    {
        $event = Event::query()->findOrFail($room->event_id);
        $eventIds = $this->validatePreviousEventIds($event, $eventIds);
        $events = Event::query()
            ->with('activity')
            ->whereIn('id', $eventIds)
            ->get()
            ->keyBy('id');

        CommunityRoulettePreviousEvent::query()
            ->where('room_id', $room->id)
            ->delete();

        foreach (array_values($eventIds) as $index => $eventId) {
            $previous = $events->get($eventId);
            if (! $previous) {
                continue;
            }

            CommunityRoulettePreviousEvent::query()->create([
                'room_id' => $room->id,
                'event_id' => $previous->id,
                'position' => $index + 1,
                'event_name_snapshot' => $previous->name ?: $previous->activity?->name ?: 'Evento',
                'event_date_snapshot' => $previous->date,
            ]);
        }
    }

    private function syncRules(CommunityRouletteRoom $room, ?array $manualResponsibilityIds = null): void
    {
        $slotTypeIds = $this->relevantSlotTypeIds($room);
        $slotTypes = SlotType::query()
            ->with('statuses:id,name')
            ->whereIn('id', $slotTypeIds)
            ->get();

        $existing = CommunityRouletteSlotTypeRule::query()
            ->where('room_id', $room->id)
            ->get()
            ->keyBy('slot_type_id');

        CommunityRouletteSlotTypeRule::query()
            ->where('room_id', $room->id)
            ->whereNotIn('slot_type_id', $slotTypeIds ?: [0])
            ->delete();

        foreach ($slotTypes as $slotType) {
            $hq = $this->isHqSlotTypeName($slotType->name);
            $defaultResponsibility = $hq || ! $slotType->statuses->contains(
                fn ($status): bool => strtoupper(trim((string) $status->name)) === 'RECLUTA'
            );

            $oldRule = $existing->get($slotType->id);

            if ($manualResponsibilityIds !== null && $oldRule) {
                // Los tipos que el creador ya veía en el formulario obedecen
                // exactamente a sus checkboxes. Si cambiar un histórico hace
                // aparecer un tipo nuevo, ese tipo entra con la detección
                // automática y podrá ajustarse en el siguiente recálculo.
                $isResponsibility = $hq || in_array((int) $slotType->id, $manualResponsibilityIds, true);
                $source = $hq ? 'fixed' : 'manual';
            } else {
                $isResponsibility = $hq
                    ? true
                    : ($oldRule?->is_responsibility ?? $defaultResponsibility);
                $source = $hq ? 'fixed' : ($oldRule?->source ?? 'auto');
            }

            CommunityRouletteSlotTypeRule::query()->updateOrCreate(
                [
                    'room_id' => $room->id,
                    'slot_type_id' => $slotType->id,
                ],
                [
                    'slot_type_name_snapshot' => $slotType->name,
                    'is_responsibility' => $isResponsibility,
                    'is_hq' => $hq,
                    'source' => $source,
                ],
            );
        }
    }

    private function recalculateCandidates(CommunityRouletteRoom $room): void
    {
        $room->loadMissing('previousEvents');
        $rules = CommunityRouletteSlotTypeRule::query()
            ->where('room_id', $room->id)
            ->get()
            ->keyBy('slot_type_id');

        $currentSlots = EventSlot::query()
            ->where('event_id', $room->event_id)
            ->whereNotNull('user_id')
            ->with(['user.status', 'slotType'])
            ->get()
            ->filter(fn (EventSlot $slot): bool => $slot->user !== null)
            ->sortBy(fn (EventSlot $slot): string => mb_strtolower((string) $slot->user?->nick))
            ->values();

        $userIds = $currentSlots->pluck('user_id')->filter()->unique()->values();
        $previousIds = $room->previousEvents->pluck('event_id')->filter()->map(fn ($id): int => (int) $id)->all();
        $previousSnapshots = $room->previousEvents->keyBy('event_id');

        $historicalSlots = collect();
        if ($userIds->isNotEmpty() && $previousIds !== []) {
            $historicalSlots = EventSlot::query()
                ->whereIn('event_id', $previousIds)
                ->whereIn('user_id', $userIds)
                ->with(['slotType'])
                ->get()
                ->keyBy(fn (EventSlot $slot): string => $slot->event_id . ':' . $slot->user_id);
        }

        $previousWinnerIds = CommunityRouletteCandidate::query()
            ->where('room_id', $room->id)
            ->where('is_winner', true)
            ->pluck('user_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->all();

        CommunityRouletteCandidate::query()
            ->where('room_id', $room->id)
            ->delete();

        $membershipCutoff = now()->subMonthsNoOverflow(3)->startOfDay();

        foreach ($currentSlots as $currentSlot) {
            $user = $currentSlot->user;
            if (! $user) {
                continue;
            }

            $statusName = strtoupper(trim((string) $user->status?->name));
            $memberAt = $user->member_at;
            $exclusions = [];
            $history = [];
            $previousResponsibilityCount = 0;
            $wasPreviousHq = false;

            foreach ($room->previousEvents as $previousSelection) {
                if (! $previousSelection->event_id) {
                    continue;
                }

                $historicalSlot = $historicalSlots->get($previousSelection->event_id . ':' . $user->id);
                if (! $historicalSlot) {
                    continue;
                }

                $rule = $rules->get($historicalSlot->slot_type_id);
                $isHq = (bool) ($rule?->is_hq)
                    || $this->isHqSlotTypeName((string) $historicalSlot->slotType?->name);
                $isResponsibility = $isHq || (bool) ($rule?->is_responsibility);

                if ($isResponsibility) {
                    $previousResponsibilityCount++;
                }

                if ($isHq) {
                    $wasPreviousHq = true;
                }

                $snapshot = $previousSnapshots->get($previousSelection->event_id);
                $history[] = [
                    'event_id' => (int) $previousSelection->event_id,
                    'event_name' => $snapshot?->event_name_snapshot ?: 'Evento',
                    'event_date' => $snapshot?->event_date_snapshot?->format('d/m/Y'),
                    'slot_name' => $historicalSlot->name,
                    'slot_type' => $historicalSlot->slotType?->name ?: 'Sin tipo',
                    'responsibility' => $isResponsibility,
                    'hq' => $isHq,
                ];
            }

            $currentRule = $rules->get($currentSlot->slot_type_id);
            $currentIsHq = (bool) ($currentRule?->is_hq)
                || $this->isHqSlotTypeName((string) $currentSlot->slotType?->name);
            $currentIsResponsibility = $currentIsHq || (bool) ($currentRule?->is_responsibility);

            if ($statusName === 'RECLUTA') {
                $exclusions[] = 'Recluta: 0 papeletas';
            }

            // La antigüedad mínima solo condiciona a los miembros ACTIVO.
            // Un RESERVA no queda fuera por tener member_at reciente o vacío.
            if ($statusName === 'ACTIVO') {
                if (! $memberAt) {
                    $exclusions[] = 'Sin fecha de alta como miembro: 0 papeletas';
                } elseif ($memberAt->startOfDay()->gt($membershipCutoff)) {
                    $exclusions[] = 'Menos de 3 meses como miembro: 0 papeletas';
                }
            }

            $wasAlreadyDrawn = in_array((int) $user->id, $previousWinnerIds, true);
            if ($wasAlreadyDrawn) {
                $exclusions[] = 'Ganador anterior descartado para repetir el sorteo';
            }

            if ($currentIsResponsibility) {
                $exclusions[] = 'Responsabilidad en la partida actual: 0 papeletas';
            }

            if ($wasPreviousHq) {
                $exclusions[] = 'Mando global en una de las 3 partidas anteriores: 0 papeletas';
            }

            $tickets = $exclusions !== []
                ? 0
                : max(1, 4 - $previousResponsibilityCount);

            CommunityRouletteCandidate::query()->create([
                'room_id' => $room->id,
                'user_id' => $user->id,
                'nick_snapshot' => $user->nick,
                'status_snapshot' => $statusName ?: null,
                'member_at_snapshot' => $memberAt,
                'current_slot_key' => $currentSlot->slot_key,
                'current_slot_name' => $currentSlot->name,
                'current_slot_type_id' => $currentSlot->slot_type_id,
                'base_tickets' => 4,
                'tickets' => $tickets,
                'previous_responsibility_count' => min(3, $previousResponsibilityCount),
                'excluded_reason' => $exclusions !== [] ? implode(' · ', $exclusions) : null,
                'details' => [
                    'current' => [
                        'slot_name' => $currentSlot->name,
                        'slot_type' => $currentSlot->slotType?->name ?: 'Sin tipo',
                        'responsibility' => $currentIsResponsibility,
                        'hq' => $currentIsHq,
                    ],
                    'history' => $history,
                    'exclusions' => $exclusions,
                ],
                'is_winner' => $wasAlreadyDrawn,
            ]);
        }
    }

    private function relevantSlotTypeIds(CommunityRouletteRoom $room): array
    {
        $event = Event::query()->findOrFail($room->event_id);
        $ids = collect($event->orbat['groups'] ?? [])
            ->filter(fn (array $group): bool => (bool) ($group['visible'] ?? true))
            ->flatMap(fn (array $group): array => collect($group['slots'] ?? [])
                ->filter(fn (array $slot): bool => (bool) ($slot['visible'] ?? true))
                ->pluck('slot_type_id')
                ->filter()
                ->all());

        $previousIds = CommunityRoulettePreviousEvent::query()
            ->where('room_id', $room->id)
            ->pluck('event_id')
            ->filter();

        if ($previousIds->isNotEmpty()) {
            $ids = $ids->merge(
                EventSlot::query()
                    ->whereIn('event_id', $previousIds)
                    ->pluck('slot_type_id')
            );
        }

        $ids->push($room->target_slot_type_id);

        return $ids
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values()
            ->all();
    }

    private function validatePreviousEventIds(Event $event, array $eventIds): array
    {
        $eventIds = array_values(array_unique(array_filter(array_map('intval', $eventIds))));

        if (count($eventIds) > 3) {
            throw ValidationException::withMessages([
                'previous_event_ids' => 'Solo pueden usarse tres eventos anteriores.',
            ]);
        }

        if ($eventIds === []) {
            return [];
        }

        $allowedIds = $this->previousEventOptions($event)
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();

        foreach ($eventIds as $eventId) {
            if (! in_array($eventId, $allowedIds, true)) {
                throw ValidationException::withMessages([
                    'previous_event_ids' => 'Uno de los eventos históricos seleccionados no es un OPERATIVO anterior válido.',
                ]);
            }
        }

        return $eventIds;
    }

    private function assertConfigurable(CommunityRouletteRoom $room, User $user): void
    {
        if (! $this->canControlRoom($user, $room)) {
            abort(403);
        }

        if (! $room->canBeConfigured()) {
            throw ValidationException::withMessages([
                'room' => 'La sala ya está girando, ha terminado o ha caducado.',
            ]);
        }
    }

    private function findVisibleOrbatSlot(Event $event, string $slotKey): ?array
    {
        foreach (($event->orbat['groups'] ?? []) as $group) {
            if (! (bool) ($group['visible'] ?? true)) {
                continue;
            }

            foreach (($group['slots'] ?? []) as $slot) {
                if (! (bool) ($slot['visible'] ?? true)) {
                    continue;
                }

                if (hash_equals((string) ($slot['slot_key'] ?? ''), $slotKey)) {
                    return [
                        'group' => $group,
                        'slot' => $slot,
                    ];
                }
            }
        }

        return null;
    }


    private function targetSnapshotMatchesRoom(CommunityRouletteRoom $room, array $targetSnapshot): bool
    {
        return (int) ($targetSnapshot['slot']['slot_type_id'] ?? 0) === (int) $room->target_slot_type_id
            && (int) ($targetSnapshot['group']['faction_id'] ?? 0) === (int) $room->target_faction_id;
    }

    private function operationTypeQuery(Builder $query): void
    {
        $query->whereRaw("UPPER(TRIM(name)) IN ('OPERACIÓN', 'OPERACION')");
    }

    private function isOperationEvent(Event $event): bool
    {
        $name = (string) $event->activity?->activityType?->name;
        $normalized = Str::of($name)->ascii()->trim()->upper()->value();

        return $normalized === 'OPERACION';
    }

    private function isHqSlotTypeName(string $name): bool
    {
        return Str::of($name)->ascii()->trim()->lower()->squish()->value() === 'mando global';
    }

    private function roomRelations(): array
    {
        return [
            'event.activity.activityType',
            'creator',
            'winner',
            'targetSlotType',
            'previousEvents',
            'rules',
            'candidates',
        ];
    }
}
