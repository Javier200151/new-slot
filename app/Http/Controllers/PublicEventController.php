<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Event;
use App\Models\Faction;
use App\Models\OperationType;
use App\Models\SlotType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Http\Request;
use Illuminate\Support\HtmlString;
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

        $events = Event::query()
            ->whereHas('eventStatus', fn ($query) => $query
                ->whereIn('name', ['ACTIVO', 'FINALIZADO']))
            ->whereBetween('date', [$monthStart->startOfDay(), $monthEnd->endOfDay()])
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
            ])
            ->orderBy('date')
            ->get();

        $eventsByDate = $events->groupBy(
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

        $listedEvents = $events
            ->when(
                $selectedTypeId,
                fn ($events) => $events->filter(
                    fn (Event $event): bool => (int) $event->operation?->operation_type_id === $selectedTypeId,
                ),
            )
            ->when(
                $selectedDateFrom && $selectedDateTo,
                fn ($events) => $events->filter(
                    fn (Event $event): bool => $event->date->toDateString() >= $selectedDateFrom
                        && $event->date->toDateString() <= $selectedDateTo,
                ),
            )
            ->sortByDesc('date')
            ->values();

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
            'slots.user',
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
            ->whereIn('id', $slotTypeIds)
            ->get()
            ->keyBy('id');
        $assignments = $event->slots->keyBy('slot_key');

        $visibleOrbatGroups = $groups
            ->map(function (array $group) use ($assignments, $factions, $slotTypes): array {
                $group['faction'] = $factions->get((int) ($group['faction_id'] ?? 0));
                $group['slots'] = collect($group['slots'] ?? [])
                    ->filter(fn (array $slot): bool => (bool) ($slot['visible'] ?? true))
                    ->map(function (array $slot) use ($assignments, $slotTypes): array {
                        $slot['slot_type'] = $slotTypes->get((int) ($slot['slot_type_id'] ?? 0));
                        $slot['assignment'] = $assignments->get($slot['slot_key'] ?? null);

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

        return view('events.show', compact(
            'addons',
            'descriptionSections',
            'event',
            'operation',
            'radioNetworks',
            'visibleOrbatGroups',
        ));
    }
}
