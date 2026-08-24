<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\Faction;
use App\Models\OperationType;
use App\Models\SlotType;
use App\Models\User;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use Illuminate\Http\JsonResponse;

class PublicEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'type' => ['nullable', 'integer', 'exists:operations_type,id'],
            'date_from' => ['nullable', 'date_format:Y-m-d'],
            'date_to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ]);

        $today = CarbonImmutable::today();
        $month = (int) ($filters['month'] ?? $today->month);
        $year = (int) ($filters['year'] ?? $today->year);
        $selectedTypeId = isset($filters['type']) ? (int) $filters['type'] : null;

        $monthStart = CarbonImmutable::create($year, $month)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $selectedDateFrom = $filters['date_from'] ?? $monthStart->toDateString();
        $selectedDateTo = $filters['date_to'] ?? $monthEnd->toDateString();
        $hasListFilters = $selectedTypeId
            || $selectedDateFrom !== $monthStart->toDateString()
            || $selectedDateTo !== $monthEnd->toDateString();

        $eventsQuery = Event::query()
            ->whereHas('eventStatus', fn ($query) => $query
                ->whereIn('name', ['ACTIVO', 'FINALIZADO']))
            ->with([
                'eventStatus',
                'eventResult',
                'operation.operationType',
                'operation.campaign',
                'operation.period',
                'operation.platform',
                'operation.map',
            ])
            ->withCount([
                'slots as occupied_slots_count' => fn ($query) => $query
                    ->where(fn ($query) => $query
                        ->whereNotNull('user_id')
                        ->orWhereNotNull('ally_id')),
            ]);

        $calendarEvents = (clone $eventsQuery)
            ->whereBetween('date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
            ->orderBy('date')
            ->get();

        $eventsByDate = $calendarEvents->groupBy(
            fn (Event $event): string => $event->date->toDateString(),
        );

        $calendarStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $calendarEnd = $monthEnd->endOfWeek(CarbonInterface::SUNDAY);
        $calendarDays = collect();

        for ($day = $calendarStart; $day->lte($calendarEnd); $day = $day->addDay()) {
            $calendarDays->push([
                'date' => $day,
                'is_current_month' => $day->month === $month,
                'is_today' => $day->isSameDay($today),
                'events' => $eventsByDate->get($day->toDateString(), collect()),
            ]);
        }

        $listedEvents = (clone $eventsQuery)
            ->whereBetween('date', [
                CarbonImmutable::parse($selectedDateFrom)->startOfDay(),
                CarbonImmutable::parse($selectedDateTo)->endOfDay(),
            ])
            ->when(
                $selectedTypeId,
                fn ($query) => $query->whereHas(
                    'operation',
                    fn ($query) => $query->where('operation_type_id', $selectedTypeId),
                ),
            )
            ->orderByDesc('date')
            ->get();

        $operationTypes = OperationType::query()
            ->orderBy('name')
            ->get(['id', 'name', 'color']);

        $monthNames = [
            1 => 'Enero',
            2 => 'Febrero',
            3 => 'Marzo',
            4 => 'Abril',
            5 => 'Mayo',
            6 => 'Junio',
            7 => 'Julio',
            8 => 'Agosto',
            9 => 'Septiembre',
            10 => 'Octubre',
            11 => 'Noviembre',
            12 => 'Diciembre',
        ];

        return view('events.index', [
            'calendarDays' => $calendarDays,
            'listedEvents' => $listedEvents,
            'operationTypes' => $operationTypes,
            'selectedTypeId' => $selectedTypeId,
            'selectedDateFrom' => $selectedDateFrom,
            'selectedDateTo' => $selectedDateTo,
            'hasListFilters' => $hasListFilters,
            'month' => $month,
            'year' => $year,
            'monthName' => $monthNames[$month],
            'monthNames' => $monthNames,
            'previousMonth' => $monthStart->subMonth(),
            'nextMonth' => $monthStart->addMonth(),
        ]);
    }

    public function show(Event $event): View
    {
        $event->load([
            'eventStatus',
            'eventResult',
            'operation.operationType',
            'operation.operationStatus',
            'operation.campaign',
            'operation.period',
            'operation.platform',
            'operation.map',
            'operation.days',
            'operation.editor',
            'operation.enemyFactions.army',
            'operation.enemyFactions.side',
            'slots.user.mainSqaGroup',
            'slots.ally',
        ]);

        abort_unless(
            in_array($event->eventStatus?->name, ['ACTIVO', 'FINALIZADO'], true),
            404,
        );

        $operation = $event->operation;
        abort_if($operation === null, 404);

        $groups = collect($event->orbat['groups'] ?? [])
            ->filter(fn (array $group): bool => (bool) ($group['visible'] ?? true));

        $factions = Faction::query()
            ->with(['army', 'side'])
            ->whereIn('id', $groups->pluck('faction_id')->filter()->unique())
            ->get()
            ->keyBy('id');

        $slotTypeIds = $groups
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_type_id')
            ->filter()
            ->unique();
        $slotTypes = SlotType::query()
            ->with('statuses:id')
            ->whereIn('id', $slotTypeIds)
            ->get()
            ->keyBy('id');
        $assignments = $event->slots->keyBy('slot_key');
        $currentUserSlot = auth()->check()
            ? $event->slots->firstWhere('user_id', auth()->id())
            : null;
        $isRegistrationOpen = $event->eventStatus?->name === 'ACTIVO';
        $canManageOrbat = $this->canManageOrbat(
            auth()->user()
        );
        $user = auth()->user();

        $isAdmin =
            $user?->hasRole('admin')
            ?? false;

        $canAccessFilament =
            $isAdmin
            || ($user?->can('filament.access') ?? false);

        $canEditOperation =
            $canAccessFilament
            && (
                $isAdmin
                || $user?->can('operations.update')
            );

        $canEditEvent =
            $canAccessFilament
            && (
                $isAdmin
                || $user?->can('events.update')
            );

        $canUseEditorMode =
            $canEditOperation
            || $canEditEvent;

        $visibleOrbatGroups = $groups
            ->map(function (array $group) use ($assignments, $currentUserSlot, $factions, $isRegistrationOpen, $slotTypes): array {
                $group['faction'] = $factions->get((int) ($group['faction_id'] ?? 0));
                $group['slots'] = collect($group['slots'] ?? [])
                    ->filter(fn (array $slot): bool => (bool) ($slot['visible'] ?? true))
                    ->map(function (array $slot) use ($assignments, $currentUserSlot, $isRegistrationOpen, $slotTypes): array {
                        $slotKey = $slot['slot_key'] ?? null;
                        $slot['slot_type'] = $slotTypes->get((int) ($slot['slot_type_id'] ?? 0));
                        $slot['assignment'] = filled($slotKey) ? $assignments->get($slotKey) : null;
                        $slot['is_occupied'] = (bool) ($slot['assignment']?->user_id || $slot['assignment']?->ally_id);
                        $slot['is_owned_by_user'] = auth()->check()
                            && (int) $slot['assignment']?->user_id === (int) auth()->id();
                        $slot['is_allowed_for_user'] = auth()->check()
                            && $slot['slot_type']?->statuses->contains('id', auth()->user()->status_id);
                        $slot['can_register'] = $isRegistrationOpen
                            && auth()->check()
                            && filled($slotKey)
                            && ! $slot['is_occupied']
                            && $slot['is_allowed_for_user'];
                        $slot['will_move_user'] = $slot['can_register'] && $currentUserSlot !== null;

                        return $slot;
                    })
                    ->values();

                return $group;
            })
            ->values();

        $description = $operation->description ?? [];
        $descriptionSections = collect($description['sections'] ?? []);

        if ($descriptionSections->isEmpty() && filled($description['content'] ?? null)) {
            $descriptionSections = collect([[
                'title' => 'Descripción',
                'content' => $description['content'],
            ]]);
        }

        $descriptionSections = $descriptionSections
        ->map(function (array $section): array {
            $position = $section['image_position'] ?? 'left';

            if (! in_array(
                $position,
                ['left', 'right', 'top', 'bottom'],
                true
            )) {
                $position = 'left';
            }

            $width = (string) ($section['image_width'] ?? '40');

            if (! in_array(
                $width,
                ['33', '40', '50', '66', '100'],
                true
            )) {
                $width = '40';
            }

            /*
            * 100% no tiene sentido con texto al lado.
            * Lo convertimos automáticamente en imagen superior.
            */
            if (
                $width === '100'
                && in_array($position, ['left', 'right'], true)
            ) {
                $position = 'top';
            }

            return [
                'title' =>
                    $section['title'] ?? 'Descripción',

                'content' => new HtmlString(
                    RichContentRenderer::make(
                        $section['content'] ?? ''
                    )->toHtml(),
                ),

                'image' =>
                    $section['image'] ?? null,

                'image_position' =>
                    $position,

                'image_width' =>
                    $width,

                'image_caption' =>
                    $section['image_caption'] ?? null,
            ];
        });

        $radioNetworks = collect($operation->radio['networks'] ?? [])
            ->filter(fn (array $network): bool => (bool) ($network['visible'] ?? true))
            ->values();

        $addons = Addon::query()
            ->whereIn('id', $operation->addons['addon_ids'] ?? [])
            ->orderByDesc('mandatory')
            ->orderBy('name')
            ->get();

        $eventComments = EventComment::query()
            ->where('event_id', $event->id)
            ->with('user.mainSqaGroup')
            ->orderByDesc('is_pinned')
            ->oldest('created_at')
            ->oldest('id')
            ->get();
        $commentsByParent = $eventComments->groupBy(
            fn (EventComment $comment): int|string => $comment->parent_id ?? 'root',
        );

        $slotHistory = EventSlotHistory::query()
            ->where('event_id', $event->id)
            ->with([
                'ally',
                'changedBy',
                'fromArmy',
                'fromSlotType',
                'toArmy',
                'toSlotType',
                'user',
            ])
            ->latest('created_at')
            ->latest('id')
            ->get();

        return view('events.show', compact(
            'addons',
            'canManageOrbat',
            'commentsByParent',
            'descriptionSections',
            'event',
            'eventComments',
            'operation',
            'radioNetworks',
            'slotHistory',
            'visibleOrbatGroups',
            'canEditEvent',
            'canEditOperation',
            'canUseEditorMode',
        ));
    }

    public function registerSlot(Event $event, string $slotKey): RedirectResponse
    {
        $user = request()->user();

        DB::transaction(function () use ($event, $slotKey, $user): void {
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedEvent->eventStatus()->where('name', 'ACTIVO')->exists()) {
                throw ValidationException::withMessages([
                    'slot' => 'Este evento no admite nuevas inscripciones.',
                ]);
            }

            $orbatSlot = $this->findVisibleOrbatSlot($lockedEvent, $slotKey);

            if ($orbatSlot === null) {
                throw ValidationException::withMessages([
                    'slot' => 'El slot seleccionado no existe o no está visible.',
                ]);
            }

            $slotTypeId = (int) ($orbatSlot['slot']['slot_type_id'] ?? 0);
            $factionId = (int) ($orbatSlot['group']['faction_id'] ?? 0);

            if (
                $slotTypeId < 1
                || ! SlotType::query()
                    ->whereKey($slotTypeId)
                    ->whereHas('statuses', fn ($query) => $query->whereKey($user->status_id))
                    ->exists()
            ) {
                throw ValidationException::withMessages([
                    'slot' => 'Tu estado actual no permite ocupar este tipo de slot.',
                ]);
            }

            if ($factionId < 1) {
                throw ValidationException::withMessages([
                    'slot' => 'El grupo del slot no tiene una facción válida.',
                ]);
            }

            $targetSlot = EventSlot::query()
                ->where('event_id', $lockedEvent->id)
                ->where('slot_key', $slotKey)
                ->lockForUpdate()
                ->first();
            $currentSlot = EventSlot::query()
                ->where('event_id', $lockedEvent->id)
                ->where('user_id', $user->id)
                ->lockForUpdate()
                ->first();

            if ($targetSlot?->user_id === $user->id) {
                return;
            }

            if ($targetSlot && ($targetSlot->user_id || $targetSlot->ally_id)) {
                throw ValidationException::withMessages([
                    'slot' => 'Este slot acaba de ser ocupado por otro usuario.',
                ]);
            }

            $targetSnapshot = [
                'slot_key' => $slotKey,
                'name' => $orbatSlot['slot']['name'] ?? 'Slot sin nombre',
                'slot_type_id' => $slotTypeId,
                'slot_group' => $orbatSlot['group']['name'] ?? 'Grupo sin nombre',
                'faction_id' => $factionId,
            ];
            $targetArmyId = Faction::query()->whereKey($factionId)->value('army_id');
            $fromSnapshot = $currentSlot ? [
                'slot_key' => $currentSlot->slot_key,
                'name' => $currentSlot->name,
                'slot_type_id' => $currentSlot->slot_type_id,
                'slot_group' => $currentSlot->slot_group,
                'army_id' => $currentSlot->faction?->army_id,
            ] : null;

            if ($targetSlot) {
                if ($currentSlot) {
                    $currentSlot->forceFill([
                        'user_id' => null,
                        'ally_id' => null,
                    ])->save();
                }

                $eventSlot = $targetSlot;
                $eventSlot->forceFill([
                    ...$targetSnapshot,
                    'user_id' => $user->id,
                    'ally_id' => null,
                ])->save();
            } elseif ($currentSlot) {
                $eventSlot = $currentSlot;
                $eventSlot->forceFill([
                    ...$targetSnapshot,
                    'user_id' => $user->id,
                    'ally_id' => null,
                ])->save();
            } else {
                $eventSlot = EventSlot::query()->create([
                    'event_id' => $lockedEvent->id,
                    ...$targetSnapshot,
                    'user_id' => $user->id,
                    'ally_id' => null,
                ]);
            }

            EventSlotHistory::query()->create([
                'event_slot_id' => $eventSlot->id,
                'event_id' => $lockedEvent->id,
                'user_id' => $user->id,
                'ally_id' => null,
                'action' => $fromSnapshot ? 'moved' : 'assigned',
                'from_slot_key' => $fromSnapshot['slot_key'] ?? null,
                'from_slot_name' => $fromSnapshot['name'] ?? null,
                'from_slot_type_id' => $fromSnapshot['slot_type_id'] ?? null,
                'from_slot_group' => $fromSnapshot['slot_group'] ?? null,
                'from_army_id' => $fromSnapshot['army_id'] ?? null,
                'to_slot_key' => $targetSnapshot['slot_key'],
                'to_slot_name' => $targetSnapshot['name'],
                'to_slot_type_id' => $targetSnapshot['slot_type_id'],
                'to_slot_group' => $targetSnapshot['slot_group'],
                'to_army_id' => $targetArmyId,
                'changed_by_user_id' => $user->id,
                'created_at' => now(),
            ]);
        });

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Tu slot se ha actualizado correctamente.');
    }

    public function unregisterSlot(Event $event, string $slotKey): RedirectResponse
    {
        $user = request()->user();

        DB::transaction(function () use ($event, $slotKey, $user): void {
            $lockedEvent = Event::query()
                ->whereKey($event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $lockedEvent->eventStatus()->where('name', 'ACTIVO')->exists()) {
                throw ValidationException::withMessages([
                    'slot' => 'Este evento ya no admite cambios en las inscripciones.',
                ]);
            }

            $eventSlot = EventSlot::query()
                ->where('event_id', $lockedEvent->id)
                ->where('slot_key', $slotKey)
                ->where('user_id', $user->id)
                ->with('faction')
                ->lockForUpdate()
                ->first();

            if (! $eventSlot) {
                throw ValidationException::withMessages([
                    'slot' => 'Ya no estás apuntado en este slot.',
                ]);
            }

            EventSlotHistory::query()->create([
                'event_slot_id' => $eventSlot->id,
                'event_id' => $lockedEvent->id,
                'user_id' => $user->id,
                'ally_id' => null,
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
                'changed_by_user_id' => $user->id,
                'created_at' => now(),
            ]);

            $eventSlot->delete();
        });

        return redirect()
            ->route('events.show', $event)
            ->with('status', 'Te has desapuntado correctamente.');
    }

    public function manageSlot(
    Event $event,
    string $slotKey,
    Request $request,
): JsonResponse|RedirectResponse {
    $manager = $request->user();

    abort_unless(
        $this->canManageOrbat($manager),
        403,
    );

    $validated = $request->validate([
        'action' => [
            'required',
            Rule::in([
                'move',
                'clear',
            ]),
        ],

        'user_id' => [
            Rule::requiredIf(
                fn (): bool =>
                    $request->input('action') === 'move'
            ),
            'nullable',
            'integer',
            Rule::exists('users', 'id')
                ->whereNull('deleted_at'),
        ],
    ]);

    $result = DB::transaction(function () use (
        $event,
        $slotKey,
        $validated,
        $manager,
    ): array {
        $lockedEvent = Event::query()
            ->whereKey($event->id)
            ->lockForUpdate()
            ->firstOrFail();

        /*
        |--------------------------------------------------------------------------
        | Comprobar que el evento permite modificar el ORBAT
        |--------------------------------------------------------------------------
        */

        if (
            ! $lockedEvent
                ->eventStatus()
                ->where('name', 'ACTIVO')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'slot' => 'El ORBAT de este evento ya no puede modificarse.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Comprobar slot destino
        |--------------------------------------------------------------------------
        */

        $orbatSlot = $this->findVisibleOrbatSlot(
            $lockedEvent,
            $slotKey,
        );

        if ($orbatSlot === null) {
            throw ValidationException::withMessages([
                'slot' => 'El slot seleccionado no existe o no está visible.',
            ]);
        }

        $slotTypeId = (int) (
            $orbatSlot['slot']['slot_type_id'] ?? 0
        );

        $factionId = (int) (
            $orbatSlot['group']['faction_id'] ?? 0
        );

        if ($slotTypeId < 1 || $factionId < 1) {
            throw ValidationException::withMessages([
                'slot' => 'El slot seleccionado no tiene una configuración válida.',
            ]);
        }

        $targetSnapshot = [
            'slot_key' => $slotKey,

            'name' =>
                $orbatSlot['slot']['name']
                ?? 'Slot sin nombre',

            'slot_type_id' => $slotTypeId,

            'slot_group' =>
                $orbatSlot['group']['name']
                ?? 'Grupo sin nombre',

            'faction_id' => $factionId,

            'army_id' => Faction::query()
                ->whereKey($factionId)
                ->value('army_id'),
        ];

        $targetSlot = EventSlot::query()
            ->where('event_id', $lockedEvent->id)
            ->where('slot_key', $slotKey)
            ->with([
                'user',
                'ally',
                'faction',
            ])
            ->lockForUpdate()
            ->first();

        /*
        |--------------------------------------------------------------------------
        | ELIMINAR jugador del ORBAT
        |--------------------------------------------------------------------------
        */

        if ($validated['action'] === 'clear') {

            if (
                ! $targetSlot
                || (
                    ! $targetSlot->user_id
                    && ! $targetSlot->ally_id
                )
            ) {
                return [
                    'action' => 'clear',
                    'slot_key' => $slotKey,
                    'message' => 'El slot ya estaba libre.',
                ];
            }

            $removedName =
                $targetSlot->user?->nick
                ?? $targetSlot->ally?->name
                ?? 'el jugador';

            $this->recordSlotUnassignment(
                $targetSlot,
                $manager->id,
            );

            $targetSlot->delete();

            return [
                'action' => 'clear',
                'slot_key' => $slotKey,
                'message' => "{$removedName} ha sido eliminado del ORBAT.",
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | MOVER jugador
        |--------------------------------------------------------------------------
        */

        $userId = (int) $validated['user_id'];

        /*
         * Buscamos el slot actual del jugador que estamos arrastrando.
         */
        $sourceSlot = EventSlot::query()
            ->where('event_id', $lockedEvent->id)
            ->where('user_id', $userId)
            ->with([
                'user',
                'faction',
            ])
            ->lockForUpdate()
            ->first();

        if (! $sourceSlot) {
            throw ValidationException::withMessages([
                'slot' => 'El jugador ya no se encuentra en el ORBAT.',
            ]);
        }

        /*
         * Lo hemos soltado encima de su propio slot.
         */
        if ($sourceSlot->slot_key === $slotKey) {
            return [
                'action' => 'move',
                'source_slot_key' => $sourceSlot->slot_key,
                'target_slot_key' => $slotKey,
                'swapped' => false,
                'message' => 'El jugador ya ocupa ese slot.',
            ];
        }

        /*
         * De momento no intercambiamos usuarios SQA
         * directamente con aliados externos.
         */
        if ($targetSlot?->ally_id) {
            throw ValidationException::withMessages([
                'slot' => 'No puedes intercambiar un usuario con un aliado externo. Vacía primero ese slot.',
            ]);
        }

        $sourceSnapshot = [
            'slot_key' => $sourceSlot->slot_key,
            'name' => $sourceSlot->name,
            'slot_type_id' => $sourceSlot->slot_type_id,
            'slot_group' => $sourceSlot->slot_group,
            'faction_id' => $sourceSlot->faction_id,
            'army_id' => $sourceSlot->faction?->army_id,
        ];

        $draggedUserName =
            $sourceSlot->user?->nick
            ?? 'Usuario';

        /*
        |--------------------------------------------------------------------------
        | DESTINO OCUPADO → INTERCAMBIO
        |--------------------------------------------------------------------------
        */

        if ($targetSlot?->user_id) {
            $targetUserId = (int) $targetSlot->user_id;

            $targetUserName =
                $targetSlot->user?->nick
                ?? 'Usuario';

            /*
             * Intercambiamos únicamente los ocupantes.
             *
             * Los registros EventSlot permanecen vinculados
             * a sus respectivos slots.
             */
            $sourceSlot->forceFill([
                'user_id' => $targetUserId,
                'ally_id' => null,
            ])->save();

            $targetSlot->forceFill([
                'user_id' => $userId,
                'ally_id' => null,
            ])->save();

            /*
             * Historial del jugador arrastrado.
             */
            $this->recordSlotMovement(
                eventSlot: $targetSlot,
                eventId: $lockedEvent->id,
                userId: $userId,
                from: $sourceSnapshot,
                to: $targetSnapshot,
                changedByUserId: $manager->id,
            );

            /*
             * Historial del jugador que estaba en el destino.
             */
            $this->recordSlotMovement(
                eventSlot: $sourceSlot,
                eventId: $lockedEvent->id,
                userId: $targetUserId,
                from: $targetSnapshot,
                to: $sourceSnapshot,
                changedByUserId: $manager->id,
            );

            return [
                'action' => 'move',
                'source_slot_key' => $sourceSnapshot['slot_key'],
                'target_slot_key' => $targetSnapshot['slot_key'],
                'swapped' => true,

                'message' =>
                    "{$draggedUserName} y {$targetUserName} han intercambiado sus slots.",
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | DESTINO LIBRE
        |--------------------------------------------------------------------------
        */

        /*
         * Puede existir un EventSlot vacío.
         */
        if ($targetSlot) {
            $sourceSlot->forceFill([
                'user_id' => null,
                'ally_id' => null,
            ])->save();

            $targetSlot->forceFill([
                'user_id' => $userId,
                'ally_id' => null,
            ])->save();

            $newEventSlot = $targetSlot;
        }

        /*
         * Si el destino no tiene registro EventSlot,
         * movemos directamente el registro actual.
         */
        else {
            $sourceSlot->forceFill([
                'slot_key' => $targetSnapshot['slot_key'],
                'name' => $targetSnapshot['name'],
                'slot_type_id' => $targetSnapshot['slot_type_id'],
                'slot_group' => $targetSnapshot['slot_group'],
                'faction_id' => $targetSnapshot['faction_id'],
                'user_id' => $userId,
                'ally_id' => null,
            ])->save();

            $newEventSlot = $sourceSlot;
        }

        $this->recordSlotMovement(
            eventSlot: $newEventSlot,
            eventId: $lockedEvent->id,
            userId: $userId,
            from: $sourceSnapshot,
            to: $targetSnapshot,
            changedByUserId: $manager->id,
        );

        return [
            'action' => 'move',
            'source_slot_key' => $sourceSnapshot['slot_key'],
            'target_slot_key' => $targetSnapshot['slot_key'],
            'swapped' => false,

            'message' =>
                "{$draggedUserName} ha sido movido correctamente.",
        ];
    });

    /*
    |--------------------------------------------------------------------------
    | Respuesta AJAX
    |--------------------------------------------------------------------------
    */

    if ($request->expectsJson()) {
        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    /*
     * Permitimos también que funcione sin JavaScript.
     */
    return redirect()
        ->route('events.show', $event)
        ->with(
            'status',
            $result['message']
                ?? 'El ORBAT se ha actualizado correctamente.',
        );
}

    public function updateComment(
        Event $event,
        EventComment $eventComment,
        Request $request,
    ): RedirectResponse {
        abort_unless(
            $event->eventStatus()->whereIn('name', ['ACTIVO', 'FINALIZADO'])->exists(),
            404,
        );
        abort_unless((int) $eventComment->event_id === (int) $event->id, 404);
        abort_unless((int) $eventComment->user_id === (int) $request->user()->id, 403);

        $validated = $request->validate([
            'comment' => ['required', 'string', 'max:5000'],
        ]);

        $eventComment->update([
            'comment' => $validated['comment'],
        ]);

        return redirect()
            ->to(route('events.show', $event).'#comentarios')
            ->with('comment_status', 'Tu comentario se ha actualizado correctamente.');
    }

    /**
     * @return array{group: array<string, mixed>, slot: array<string, mixed>}|null
     */
    private function findVisibleOrbatSlot(Event $event, string $slotKey): ?array
    {
        foreach ($event->orbat['groups'] ?? [] as $group) {
            if (! (bool) ($group['visible'] ?? true)) {
                continue;
            }

            foreach ($group['slots'] ?? [] as $slot) {
                if (
                    (bool) ($slot['visible'] ?? true)
                    && hash_equals((string) ($slot['slot_key'] ?? ''), $slotKey)
                ) {
                    return compact('group', 'slot');
                }
            }
        }

        return null;
    }
    private function canManageOrbat(?User $user): bool
    {
        if (! $user) {
            return false;
        }

        /*
        * Dejamos admin como respaldo para no bloquearlo
        * aunque todavía no se haya ejecutado el seeder
        * de este nuevo permiso.
        */
        return $user->hasRole('admin')
            || $user->can('event-orbat.manage');
    }

    private function recordSlotUnassignment(
        EventSlot $eventSlot,
        int $changedByUserId,
    ): void {
        $eventSlot->loadMissing('faction');

        EventSlotHistory::query()->create([
            'event_slot_id' => $eventSlot->id,

            'event_id' => $eventSlot->event_id,

            'user_id' => $eventSlot->user_id,

            'ally_id' => $eventSlot->ally_id,

            'action' => 'unassigned',

            'from_slot_key' =>
                $eventSlot->slot_key,

            'from_slot_name' =>
                $eventSlot->name,

            'from_slot_type_id' =>
                $eventSlot->slot_type_id,

            'from_slot_group' =>
                $eventSlot->slot_group,

            'from_army_id' =>
                $eventSlot->faction?->army_id,

            'to_slot_key' => null,

            'to_slot_name' => null,

            'to_slot_type_id' => null,

            'to_slot_group' => null,

            'to_army_id' => null,

            'changed_by_user_id' =>
                $changedByUserId,

            'created_at' => now(),
        ]);
    }
    private function recordSlotMovement(
        EventSlot $eventSlot,
        int $eventId,
        int $userId,
        array $from,
        array $to,
        int $changedByUserId,
    ): void {
        EventSlotHistory::query()->create([
            'event_slot_id' => $eventSlot->id,

            'event_id' => $eventId,

            'user_id' => $userId,

            'ally_id' => null,

            'action' => 'moved',

            'from_slot_key' =>
                $from['slot_key'] ?? null,

            'from_slot_name' =>
                $from['name'] ?? null,

            'from_slot_type_id' =>
                $from['slot_type_id'] ?? null,

            'from_slot_group' =>
                $from['slot_group'] ?? null,

            'from_army_id' =>
                $from['army_id'] ?? null,

            'to_slot_key' =>
                $to['slot_key'] ?? null,

            'to_slot_name' =>
                $to['name'] ?? null,

            'to_slot_type_id' =>
                $to['slot_type_id'] ?? null,

            'to_slot_group' =>
                $to['slot_group'] ?? null,

            'to_army_id' =>
                $to['army_id'] ?? null,

            'changed_by_user_id' =>
                $changedByUserId,

            'created_at' => now(),
        ]);
    }
}
