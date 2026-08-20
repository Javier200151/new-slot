<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\Faction;
use App\Models\OperationType;
use App\Models\SlotType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

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
            'operation.day',
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
            ->map(fn (array $section): array => [
                'title' => $section['title'] ?? 'Descripción',
                'content' => new HtmlString(
                    RichContentRenderer::make($section['content'] ?? '')->toHtml(),
                ),
            ]);

        $radioNetworks = collect($operation->radio['networks'] ?? [])
            ->filter(fn (array $network): bool => (bool) ($network['visible'] ?? true))
            ->values();

        $addons = Addon::query()
            ->whereIn('id', $operation->addons['addon_ids'] ?? [])
            ->orderByDesc('mandatory')
            ->orderBy('name')
            ->get();

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
            'descriptionSections',
            'event',
            'operation',
            'radioNetworks',
            'slotHistory',
            'visibleOrbatGroups',
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
}
