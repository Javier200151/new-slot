<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Event;
use App\Models\EventComment;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\Faction;
use App\Models\ActivityType;
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
use App\Notifications\EventCommentReplyNotification;
use App\Notifications\EventSlotChangedNotification;
use App\Models\Ally;
use App\Models\Stream;
use App\Models\EventMedia;
use App\Filament\Resources\Events\EventResource;
use App\Services\CourseMetopaAwardService;
use App\Support\ActivityTypeAccess;

class PublicEventController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'month' => ['nullable', 'integer', 'between:1,12'],
            'year' => ['nullable', 'integer', 'between:2000,2100'],
            'type' => ['nullable', 'integer', 'exists:activity_types,id'],
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
                ->whereIn('name', ['ACTIVO', 'FINALIZADO', 'CANCELADO', 'BORRADOR']))
            ->with([
                'eventStatus',
                'eventResult',
                'activity.activityType',
                'activity.campaign',
                'activity.period',
                'activity.platform',
                'activity.map',
                'slots:id,event_id,slot_key,user_id,ally_id',
                'slots.ally:id,name,image,url',
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

        $this->setVisibleOccupiedSlotsCount(
            $calendarEvents
        );
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
                    'activity',
                    fn ($query) => $query->where('activity_type_id', $selectedTypeId),
                ),
            )
            ->orderByDesc('date')
            ->get();

        $this->setVisibleOccupiedSlotsCount(
            $listedEvents
        );

        $activityTypes = ActivityType::query()
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
            'activityTypes' => $activityTypes,
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
            'activity.activityType',
            'activity.activityStatus',
            'activity.campaign',
            'activity.period',
            'activity.platform',
            'activity.map',
            'activity.days',
            'activity.editor',
            'activity.editorAlly',
            'activity.metopa',
            'activity.enemyFactions.army.country',
            'activity.enemyFactions.side',
            'slots.user.mainSqaGroup',
            'slots.ally',
        ]);

        // MySQL suele comparar los estados sin distinguir mayúsculas/minúsculas,
        // pero PHP sí lo hace. Normalizamos aquí para que un BORRADOR (aunque
        // el nombre haya sido guardado como "Borrador" o con espacios) no termine
        // en un 404 al abrir su ficha pública.
        $eventStatusName = strtoupper(trim((string) $event->eventStatus?->name));

        abort_unless(
            in_array($eventStatusName, ['ACTIVO', 'FINALIZADO', 'CANCELADO', 'BORRADOR'], true),
            404,
        );

        // Los borradores se pueden consultar, pero la ficha es completamente
        // informativa: nada del ORBAT, comentarios o edición queda habilitado.
        $isReadOnly = $eventStatusName === 'BORRADOR';

        // Compatibilidad con eventos creados antes de que el ORBAT usase slot_key.
        if (! $isReadOnly) {
            $event->ensureOrbatSlotKeys();
        }

        $activity = $event->activity;
        abort_if($activity === null, 404);

        /*
        |--------------------------------------------------------------------------
        | Emisiones activas de este evento
        |--------------------------------------------------------------------------
        |
        | Utilizamos exactamente el mismo criterio que la página /directos:
        |
        | - Stream habilitado.
        | - Streamer habilitado.
        | - Asociado a este evento.
        |
        */

        $activeEventStreams =
            Stream::query()
                ->where(
                    'event_id',
                    $event->id
                )
                ->where(
                    'enabled',
                    true
                )
                ->whereHas(
                    'streamer',
                    fn ($query) =>
                        $query->where(
                            'enable',
                            true
                        )
                )
                ->with(
                    'streamer.user'
                )
                ->orderByDesc(
                    'started_at'
                )
                ->get();

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
        $isRegistrationOpen = ! $isReadOnly
            && $eventStatusName === 'ACTIVO';
        $canManageOrbat = ! $isReadOnly && $this->canManageOrbat(
            auth()->user(),
            $event,
        );
        /*
        |--------------------------------------------------------------------------
        | Personas / aliados asignables manualmente al ORBAT
        |--------------------------------------------------------------------------
        |
        | Solo los necesitamos para gestores del ORBAT y eventos ACTIVOS.
        |
        | Los usuarios que ya están ocupando un slot no aparecen en la lista,
        | porque para ellos ya tenemos la función de arrastrar/mover.
        |
        | Los aliados SÍ pueden aparecer siempre porque un mismo clan puede
        | ocupar tantos slots como sea necesario.
        |
        */

        $orbatAssignableUsers = collect();

        $orbatAssignableAllies = collect();

        if (
            $canManageOrbat
            && $event->eventStatus?->name === 'ACTIVO'
        ) {
            $assignedUserIds =
                $event->slots
                    ->pluck('user_id')
                    ->filter()
                    ->map(
                        fn ($id): int =>
                            (int) $id
                    )
                    ->values()
                    ->all();

            $orbatAssignableUsers =
                User::query()
                    ->when(
                        $assignedUserIds !== [],
                        fn ($query) =>
                            $query->whereNotIn(
                                'id',
                                $assignedUserIds
                            )
                    )
                    ->orderBy('nick')
                    ->get([
                        'id',
                        'nick',
                    ]);

            $orbatAssignableAllies =
                Ally::query()
                    ->orderBy('name')
                    ->get([
                        'id',
                        'name',
                        'image',
                    ]);
        }
        $user = auth()->user();

        $isAdmin =
            $user?->hasRole('admin')
            ?? false;

        $canAccessFilament =
            $isAdmin
            || ($user?->can('filament.access') ?? false);

        $canEditActivity = ! $isReadOnly &&
            $canAccessFilament
            && (
                $isAdmin
                || (
                    $user
                    && $event->activity
                    && $user->can(
                        'update',
                        $event->activity
                    )
                )
            );

        $canEditEvent = ! $isReadOnly &&
            $canAccessFilament
            && (
                $isAdmin
                || (
                    $user
                    && $user->can(
                        'update',
                        $event
                    )
                )
            );

        $courseMetopaService = app(CourseMetopaAwardService::class);

        $canAwardCourseMetopa =
            $canEditEvent
            && $courseMetopaService->canAwardForUser(
                $event,
                $user,
            );

        $courseMetopaAwardUrl = $canAwardCourseMetopa
            ? EventResource::getUrl(
                'edit',
                [
                    'record' => $event,
                    'awardCourseMetopa' => 1,
                ],
            )
            : null;

        /*
        |--------------------------------------------------------------------------
        | Multimedia del evento
        |--------------------------------------------------------------------------
        */

        $eventMedia =
            EventMedia::query()
                ->where('event_id', $event->id)
                ->with('user')
                ->latest('created_at')
                ->latest('id')
                ->get();

        $eventClips =
            $eventMedia
                ->where(
                    'type',
                    EventMedia::TYPE_CLIP
                )
                ->values();

        $eventVods =
            $eventMedia
                ->where(
                    'type',
                    EventMedia::TYPE_VOD
                )
                ->values();


        /*
        |--------------------------------------------------------------------------
        | Permisos multimedia
        |--------------------------------------------------------------------------
        */

        $canAddEventMedia =
            $this->canAddEventMedia(
                $event,
                $user
            );

        $canModerateEventMedia =
            $this->canModerateEventMedia(
                $event,
                $user
            );

        $canUseEditorMode =
            $canEditActivity
            || $canEditEvent;

        $visibleOrbatGroups = $groups
            ->map(function (array $group) use ($assignments, $currentUserSlot, $factions, $isRegistrationOpen, $slotTypes): array {
                $group['faction'] = $factions->get((int) ($group['faction_id'] ?? 0));
                $group['slots'] = collect($group['slots'] ?? [])
                    ->filter(fn (array $slot): bool => (bool) ($slot['visible'] ?? true))
                    ->map(function (array $slot) use ($assignments, $currentUserSlot, $isRegistrationOpen, $slotTypes): array {
                        $slotKey = $slot['slot_key'] ?? null;
                        $slot['slot_key'] = $slotKey;
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

        $description = $activity->description ?? [];
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

            $alignment = $section['image_alignment'] ?? 'left';

            if (! in_array(
                $alignment,
                ['left', 'center', 'right'],
                true
            )) {
                $alignment = 'left';
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

                'image_alignment' =>
                    $alignment,

                'image_width' =>
                    $width,

                'image_caption' =>
                    $section['image_caption'] ?? null,
            ];
        });

        $radioNetworks = collect($activity->radio['networks'] ?? [])
            ->filter(fn (array $network): bool => (bool) ($network['visible'] ?? true))
            ->values();

        $addons = Addon::query()
            ->whereIn('id', $activity->addons['addon_ids'] ?? [])
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
            'activity',
            'radioNetworks',
            'slotHistory',
            'visibleOrbatGroups',
            'canEditEvent',
            'canEditActivity',
            'canUseEditorMode',
            'orbatAssignableUsers',
            'orbatAssignableAllies',
            'activeEventStreams',
            'eventClips',
            'eventVods',
            'canAddEventMedia',
            'canModerateEventMedia',
            'canAwardCourseMetopa',
            'courseMetopaAwardUrl',
            'isReadOnly',
        ));
    }

    /*
    |--------------------------------------------------------------------------
    | AÑADIR CLIP / VOD
    |--------------------------------------------------------------------------
    */

    public function storeMedia(
        Event $event,
        Request $request,
    ): RedirectResponse {

        $event->loadMissing(
            'eventStatus'
        );

        $user =
            $request->user();


        /*
        |--------------------------------------------------------------------------
        | Solo eventos finalizados
        |--------------------------------------------------------------------------
        */

        if (
            $event->eventStatus?->name
            !== 'FINALIZADO'
        ) {
            throw ValidationException::withMessages([
                'media' =>
                    'Solo se puede añadir contenido multimedia a eventos finalizados.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Solo Streamers habilitados
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $this->canAddEventMedia(
                $event,
                $user
            ),
            403
        );


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated =
            $request->validate([
                'type' => [
                    'required',

                    Rule::in([
                        EventMedia::TYPE_CLIP,
                        EventMedia::TYPE_VOD,
                    ]),
                ],

                'title' => [
                    'required',
                    'string',
                    'max:160',
                ],

                'url' => [
                    'required',
                    'string',
                    'url:http,https',
                    'max:500',
                ],
            ]);


        /*
        |--------------------------------------------------------------------------
        | Analizar URL
        |--------------------------------------------------------------------------
        */

        $parsed =
            $this->parseEventMediaUrl(
                $validated['url'],
                $validated['type']
            );


        /*
        |--------------------------------------------------------------------------
        | Evitar duplicados
        |--------------------------------------------------------------------------
        */

        $alreadyExists =
            EventMedia::query()
                ->where(
                    'event_id',
                    $event->id
                )
                ->where(
                    'url',
                    $parsed['url']
                )
                ->exists();

        if ($alreadyExists) {
            throw ValidationException::withMessages([
                'url' =>
                    'Este enlace ya ha sido añadido al evento.',
            ]);
        }


        /*
        |--------------------------------------------------------------------------
        | Crear
        |--------------------------------------------------------------------------
        */

        EventMedia::query()->create([
            'event_id' =>
                $event->id,

            'user_id' =>
                $user->id,

            'type' =>
                $validated['type'],

            'provider' =>
                $parsed['provider'],

            'url' =>
                $parsed['url'],

            'external_id' =>
                $parsed['external_id'],

            'title' =>
                trim(
                    $validated['title']
                ),
        ]);


        /*
        |--------------------------------------------------------------------------
        | Volver a multimedia
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->to(
                route(
                    'events.show',
                    $event
                )
                . '#multimedia'
            )
            ->with(
                'media_status',
                $validated['type']
                    === EventMedia::TYPE_CLIP
                        ? 'El clip se ha añadido correctamente.'
                        : 'El VOD se ha añadido correctamente.'
            );
    }

    /*
    |--------------------------------------------------------------------------
    | ELIMINAR CLIP / VOD
    |--------------------------------------------------------------------------
    */

    public function destroyMedia(
        Event $event,
        EventMedia $eventMedia,
        Request $request,
    ): RedirectResponse {

        /*
        * Evitamos acceder a un medio
        * perteneciente a otro evento.
        */

        abort_unless(
            (int) $eventMedia->event_id
                === (int) $event->id,
            404
        );


        $event->loadMissing('eventStatus');

        abort_unless($event->eventStatus?->name === 'FINALIZADO', 404);

        $user =
            $request->user();


        /*
        * Puede eliminar:
        *
        * - quien añadió el contenido;
        * - admin;
        * - permiso de modificar el tipo de este evento.
        */

        abort_unless(
            $this->canDeleteEventMedia(
                $eventMedia,
                $user
            ),
            403
        );


        $eventMedia->delete();


        return redirect()
            ->to(
                route(
                    'events.show',
                    $event
                )
                . '#multimedia'
            )
            ->with(
                'media_status',
                'El contenido multimedia se ha eliminado.'
            );
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
            ->to(route('events.show', $event) . '#orbat')
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
            ->to(route('events.show', $event) . '#orbat')
            ->with('status', 'Te has desapuntado correctamente.');
    }

    public function manageSlot(
    Event $event,
    string $slotKey,
    Request $request,
): JsonResponse|RedirectResponse {
    $manager = $request->user();

    abort_unless(
        $this->canManageOrbat(
            $manager,
            $event,
        ),
        403,
    );

    $validated = $request->validate([
        'action' => [
            'required',
            Rule::in([
                'move',
                'clear',
                'assign',
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
        'assignee_type' => [
            Rule::requiredIf(
                fn (): bool =>
                    $request->input('action')
                    === 'assign'
            ),
            'nullable',
            Rule::in([
                'user',
                'ally',
            ]),
        ],

        'assignee_id' => [
            Rule::requiredIf(
                fn (): bool =>
                    $request->input('action')
                    === 'assign'
            ),
            'nullable',
            'integer',
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

            /*
            * Guardamos el usuario antes de eliminar
            * el registro EventSlot.
            *
            * Si es un aliado externo, será null y
            * simplemente no habrá notificación.
            */
            $removedUser = $targetSlot->user;

            $removedName =
                $removedUser?->nick
                ?? $targetSlot->ally?->name
                ?? 'el jugador';

            $this->recordSlotUnassignment(
                $targetSlot,
                $manager->id,
            );

            /*
            * Si hemos eliminado a un usuario SQA
            * y no es el propio administrador,
            * le notificamos.
            */
            if (
                $removedUser
                && (int) $removedUser->id
                    !== (int) $manager->id
            ) {
                $removedUser->notify(
                    new EventSlotChangedNotification(
                        event: $lockedEvent,
                        action: 'removed',
                        changedBy: $manager,

                        fromSlotName:
                            $targetSlot->name,

                        fromSlotGroup:
                            $targetSlot->slot_group,
                    )
                );
            }

            $targetSlot->delete();

            return [
                'action' => 'clear',
                'slot_key' => $slotKey,
                'message' =>
                    "{$removedName} ha sido eliminado del ORBAT.",
            ];
        }

        /*
        |--------------------------------------------------------------------------
        | ASIGNAR manualmente miembro / aliado
        |--------------------------------------------------------------------------
        */

        if ($validated['action'] === 'assign') {

            /*
            * Solo permitimos asignar sobre un slot libre.
            *
            * Si está ocupado, primero se elimina con la X.
            */

            if (
                $targetSlot
                && (
                    $targetSlot->user_id
                    || $targetSlot->ally_id
                )
            ) {
                throw ValidationException::withMessages([
                    'slot' =>
                        'Ese slot ya está ocupado. '
                        .'Vacía primero el slot antes de asignar otro jugador.',
                ]);
            }


            $assigneeType =
                (string) $validated[
                    'assignee_type'
                ];

            $assigneeId =
                (int) $validated[
                    'assignee_id'
                ];


            /*
            |--------------------------------------------------------------------------
            | Resolver ocupante
            |--------------------------------------------------------------------------
            */

            $assignedUser = null;

            $assignedAlly = null;


            /*
            |--------------------------------------------------------------------------
            | Miembro SQA
            |--------------------------------------------------------------------------
            */

            if ($assigneeType === 'user') {

                $assignedUser =
                    User::query()
                        ->whereKey($assigneeId)
                        ->first();

                if (! $assignedUser) {
                    throw ValidationException::withMessages([
                        'slot' =>
                            'El usuario seleccionado no existe.',
                    ]);
                }


                /*
                * Un miembro solo puede estar una vez en el ORBAT.
                *
                * Si ya está apuntado, para cambiarlo de sitio
                * utilizamos el sistema existente de arrastrar.
                */

                $alreadyAssigned =
                    EventSlot::query()
                        ->where(
                            'event_id',
                            $lockedEvent->id
                        )
                        ->where(
                            'user_id',
                            $assignedUser->id
                        )
                        ->lockForUpdate()
                        ->exists();

                if ($alreadyAssigned) {
                    throw ValidationException::withMessages([
                        'slot' =>
                            "{$assignedUser->nick} ya está en el ORBAT. "
                            .'Puedes moverlo arrastrándolo a otro slot.',
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Aliado
            |--------------------------------------------------------------------------
            |
            | Un mismo aliado puede ocupar varios slots.
            |
            | Ejemplo:
            |
            | ARMADOS
            | ARMADOS
            | ARMADOS
            | ARMADOS
            | ARMADOS
            |
            */

            else {

                $assignedAlly =
                    Ally::query()
                        ->whereKey($assigneeId)
                        ->first();

                if (! $assignedAlly) {
                    throw ValidationException::withMessages([
                        'slot' =>
                            'El aliado seleccionado no existe.',
                    ]);
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Crear / reutilizar EventSlot
            |--------------------------------------------------------------------------
            */

            $assignmentData = [
                'slot_key' =>
                    $targetSnapshot['slot_key'],

                'name' =>
                    $targetSnapshot['name'],

                'slot_type_id' =>
                    $targetSnapshot['slot_type_id'],

                'slot_group' =>
                    $targetSnapshot['slot_group'],

                'faction_id' =>
                    $targetSnapshot['faction_id'],

                'user_id' =>
                    $assignedUser?->id,

                'ally_id' =>
                    $assignedAlly?->id,
            ];


            /*
            * Puede existir un EventSlot vacío.
            */

            if ($targetSlot) {

                $targetSlot
                    ->forceFill(
                        $assignmentData
                    )
                    ->save();

                $eventSlot =
                    $targetSlot;
            }

            /*
            * Si todavía no existe registro,
            * lo creamos.
            */

            else {

                $eventSlot =
                    EventSlot::query()
                        ->create([
                            'event_id' =>
                                $lockedEvent->id,

                            ...$assignmentData,
                        ]);
            }


            if (
                $assignedAlly
                && ! $lockedEvent->multiclans
            ) {
                $lockedEvent->forceFill([
                    'multiclans' => true,
                ])->save();
            }


            /*
            |--------------------------------------------------------------------------
            | Historial
            |--------------------------------------------------------------------------
            */

            EventSlotHistory::query()->create([
                'event_slot_id' =>
                    $eventSlot->id,

                'event_id' =>
                    $lockedEvent->id,

                'user_id' =>
                    $assignedUser?->id,

                'ally_id' =>
                    $assignedAlly?->id,

                'action' =>
                    'assigned',

                'from_slot_key' =>
                    null,

                'from_slot_name' =>
                    null,

                'from_slot_type_id' =>
                    null,

                'from_slot_group' =>
                    null,

                'from_army_id' =>
                    null,

                'to_slot_key' =>
                    $targetSnapshot['slot_key'],

                'to_slot_name' =>
                    $targetSnapshot['name'],

                'to_slot_type_id' =>
                    $targetSnapshot[
                        'slot_type_id'
                    ],

                'to_slot_group' =>
                    $targetSnapshot[
                        'slot_group'
                    ],

                'to_army_id' =>
                    $targetSnapshot[
                        'army_id'
                    ],

                'changed_by_user_id' =>
                    $manager->id,

                'created_at' =>
                    now(),
            ]);


            /*
            |--------------------------------------------------------------------------
            | Notificación al miembro
            |--------------------------------------------------------------------------
            |
            | Los aliados son clanes y no tienen cuenta,
            | por lo que solo notificamos a miembros SQA.
            */

            if (
                $assignedUser
                && (int) $assignedUser->id
                    !== (int) $manager->id
            ) {
                $assignedUser->notify(
                    new EventSlotChangedNotification(
                        event:
                            $lockedEvent,

                        action:
                            'assigned',

                        changedBy:
                            $manager,

                        toSlotName:
                            $targetSnapshot[
                                'name'
                            ],

                        toSlotGroup:
                            $targetSnapshot[
                                'slot_group'
                            ],
                    )
                );
            }


            $assignedName =
                $assignedUser?->nick
                ?? $assignedAlly?->name
                ?? 'Jugador';


            return [
                'action' =>
                    'assign',

                'slot_key' =>
                    $slotKey,

                'message' =>
                    "{$assignedName} ha sido asignado al slot correctamente.",
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

        $draggedUser = $sourceSlot->user;

        $draggedUserName =
            $draggedUser?->nick
            ?? 'Usuario';

        /*
        |--------------------------------------------------------------------------
        | DESTINO OCUPADO → INTERCAMBIO
        |--------------------------------------------------------------------------
        */

        if ($targetSlot?->user_id) {
            $targetUserId = (int) $targetSlot->user_id;

            $targetUser = $targetSlot->user;

            $targetUserName =
                $targetUser?->nick
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

            /*
            |--------------------------------------------------------------------------
            | Notificar al usuario arrastrado
            |--------------------------------------------------------------------------
            */

            if (
                $draggedUser
                && (int) $draggedUser->id
                    !== (int) $manager->id
            ) {
                $draggedUser->notify(
                    new EventSlotChangedNotification(
                        event: $lockedEvent,
                        action: 'moved',
                        changedBy: $manager,

                        fromSlotName:
                            $sourceSnapshot['name'],

                        fromSlotGroup:
                            $sourceSnapshot['slot_group'],

                        toSlotName:
                            $targetSnapshot['name'],

                        toSlotGroup:
                            $targetSnapshot['slot_group'],
                    )
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Notificar al usuario que ocupaba el destino
            |--------------------------------------------------------------------------
            */

            if (
                $targetUser
                && (int) $targetUser->id
                    !== (int) $manager->id
            ) {
                $targetUser->notify(
                    new EventSlotChangedNotification(
                        event: $lockedEvent,
                        action: 'moved',
                        changedBy: $manager,

                        fromSlotName:
                            $targetSnapshot['name'],

                        fromSlotGroup:
                            $targetSnapshot['slot_group'],

                        toSlotName:
                            $sourceSnapshot['name'],

                        toSlotGroup:
                            $sourceSnapshot['slot_group'],
                    )
                );
            }

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

        if (
            $draggedUser
            && (int) $draggedUser->id
                !== (int) $manager->id
        ) {
            $draggedUser->notify(
                new EventSlotChangedNotification(
                    event: $lockedEvent,
                    action: 'moved',
                    changedBy: $manager,

                    fromSlotName:
                        $sourceSnapshot['name'],

                    fromSlotGroup:
                        $sourceSnapshot['slot_group'],

                    toSlotName:
                        $targetSnapshot['name'],

                    toSlotGroup:
                        $targetSnapshot['slot_group'],
                )
            );
        }

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
    public function storeComment(
        Event $event,
        Request $request,
    ): RedirectResponse {
        /*
        |--------------------------------------------------------------------------
        | El evento debe ser público
        |--------------------------------------------------------------------------
        */

        abort_unless(
            $event
                ->eventStatus()
                ->whereIn('name', [
                    'ACTIVO',
                    'FINALIZADO',
                ])
                ->exists(),
            404,
        );


        /*
        |--------------------------------------------------------------------------
        | Validación
        |--------------------------------------------------------------------------
        */

        $validated = $request->validate([
            'comment' => [
                'required',
                'string',
                'max:5000',
            ],

            'parent_id' => [
                'nullable',
                'integer',

                Rule::exists(
                    'event_comments',
                    'id'
                )->where(
                    fn ($query) => $query
                        ->where(
                            'event_id',
                            $event->id
                        )
                        ->whereNull('deleted_at')
                ),
            ],
        ]);


        /*
        |--------------------------------------------------------------------------
        | Crear comentario
        |--------------------------------------------------------------------------
        */

        $comment = EventComment::create([
            'event_id' => $event->id,
            'user_id' => $request->user()->id,
            'parent_id' => $validated['parent_id'] ?? null,
            'comment' => $validated['comment'],
        ]);

        if ($comment->parent_id !== null) {
            $parentComment = EventComment::query()
                ->with('user')
                ->find($comment->parent_id);

            if (
                $parentComment?->user
                && $parentComment->user_id !== $request->user()->id
            ) {
                $parentComment->user->notify(
                    new EventCommentReplyNotification(
                        $comment
                    )
                );
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Volver directamente a comentarios
        |--------------------------------------------------------------------------
        */

        return redirect()
            ->to(
                route(
                    'events.show',
                    $event
                ) . '#comentarios'
            )
            ->with(
                'comment_status',
                isset($validated['parent_id'])
                    ? 'Tu respuesta se ha publicado correctamente.'
                    : 'Tu comentario se ha publicado correctamente.'
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

    /*
    |--------------------------------------------------------------------------
    | PERMISOS MULTIMEDIA
    |--------------------------------------------------------------------------
    */

    private function canAddEventMedia(
        Event $event,
        ?User $user,
    ): bool {

        if (! $user) {
            return false;
        }


        /*
        * Multimedia únicamente cuando
        * el evento ha finalizado.
        */

        if (
            $event->eventStatus?->name
            !== 'FINALIZADO'
        ) {
            return false;
        }


        /*
        * Cargar perfil Streamer.
        */

        $user->loadMissing(
            'streamer'
        );


        /*
        * Solo Streamers habilitados.
        */

        return (bool)
            $user->streamer?->enable;
    }

    /*
    |--------------------------------------------------------------------------
    | ANALIZAR URL MULTIMEDIA
    |--------------------------------------------------------------------------
    |
    | Devuelve:
    |
    | [
    |     'provider'    => 'youtube' | 'twitch',
    |     'external_id' => '...',
    |     'url'         => URL normalizada,
    | ]
    |
    */

    private function parseEventMediaUrl(
        string $url,
        string $type,
    ): array {

        $url =
            trim($url);

        $host =
            strtolower(
                (string) parse_url(
                    $url,
                    PHP_URL_HOST
                )
            );

        $host =
            preg_replace(
                '/^(www\.|m\.)/',
                '',
                $host
            );

        $path =
            trim(
                (string) parse_url(
                    $url,
                    PHP_URL_PATH
                ),
                '/'
            );

        $segments =
            array_values(
                array_filter(
                    explode(
                        '/',
                        $path
                    ),
                    fn (string $value): bool =>
                        $value !== ''
                )
            );


        /*
        |--------------------------------------------------------------------------
        | YOUTUBE
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $host,
                [
                    'youtube.com',
                    'youtu.be',
                    'youtube-nocookie.com',
                ],
                true
            )
        ) {
            $videoId = null;


            /*
            * youtu.be/VIDEO_ID
            */

            if (
                $host === 'youtu.be'
            ) {
                $videoId =
                    $segments[0]
                    ?? null;
            }


            /*
            * youtube.com/watch?v=VIDEO_ID
            */

            elseif (
                ($segments[0] ?? null)
                === 'watch'
            ) {
                parse_str(
                    (string) parse_url(
                        $url,
                        PHP_URL_QUERY
                    ),
                    $query
                );

                $videoId =
                    $query['v']
                    ?? null;
            }


            /*
            * youtube.com/shorts/VIDEO_ID
            *
            * youtube.com/embed/VIDEO_ID
            *
            * youtube.com/live/VIDEO_ID
            */

            elseif (
                in_array(
                    $segments[0] ?? null,
                    [
                        'shorts',
                        'embed',
                        'live',
                    ],
                    true
                )
            ) {
                $videoId =
                    $segments[1]
                    ?? null;
            }


            /*
            * Los enlaces /clip/ nativos de YouTube
            * necesitan información adicional para
            * construir correctamente el reproductor.
            */

            elseif (
                ($segments[0] ?? null)
                === 'clip'
            ) {
                throw ValidationException::withMessages([
                    'url' =>
                        'Para YouTube utiliza el enlace del vídeo asociado al clip.',
                ]);
            }


            /*
            * Validar ID.
            */

            if (
                ! is_string($videoId)
                || ! preg_match(
                    '/^[A-Za-z0-9_-]{11}$/',
                    $videoId
                )
            ) {
                throw ValidationException::withMessages([
                    'url' =>
                        'No se ha podido reconocer el vídeo de YouTube.',
                ]);
            }


            return [
                'provider' =>
                    EventMedia::PROVIDER_YOUTUBE,

                'external_id' =>
                    $videoId,

                /*
                * Guardamos siempre una URL
                * canónica.
                */

                'url' =>
                    'https://www.youtube.com/watch?v='
                    . $videoId,
            ];
        }


        /*
        |--------------------------------------------------------------------------
        | TWITCH
        |--------------------------------------------------------------------------
        */

        if (
            in_array(
                $host,
                [
                    'twitch.tv',
                    'clips.twitch.tv',
                ],
                true
            )
        ) {

            /*
            |--------------------------------------------------------------------------
            | CLIP
            |--------------------------------------------------------------------------
            |
            | Formatos:
            |
            | clips.twitch.tv/SLUG
            |
            | twitch.tv/CANAL/clip/SLUG
            |
            */

            $clipSlug = null;


            if (
                $host
                === 'clips.twitch.tv'
            ) {
                $clipSlug =
                    $segments[0]
                    ?? null;
            }


            elseif (
                isset(
                    $segments[1],
                    $segments[2]
                )
                && strtolower(
                    $segments[1]
                ) === 'clip'
            ) {
                $clipSlug =
                    $segments[2];
            }


            if (
                filled($clipSlug)
            ) {

                if (
                    $type
                    !== EventMedia::TYPE_CLIP
                ) {
                    throw ValidationException::withMessages([
                        'url' =>
                            'Este enlace de Twitch corresponde a un clip, no a un VOD.',
                    ]);
                }


                if (
                    ! preg_match(
                        '/^[A-Za-z0-9_-]+$/',
                        $clipSlug
                    )
                ) {
                    throw ValidationException::withMessages([
                        'url' =>
                            'El enlace del clip de Twitch no es válido.',
                    ]);
                }


                return [
                    'provider' =>
                        EventMedia::PROVIDER_TWITCH,

                    'external_id' =>
                        $clipSlug,

                    'url' =>
                        'https://clips.twitch.tv/'
                        . $clipSlug,
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | VOD
            |--------------------------------------------------------------------------
            |
            | twitch.tv/videos/123456789
            |
            */

            if (
                ($segments[0] ?? null)
                === 'videos'
            ) {

                if (
                    $type
                    !== EventMedia::TYPE_VOD
                ) {
                    throw ValidationException::withMessages([
                        'url' =>
                            'Este enlace de Twitch corresponde a un VOD, no a un clip.',
                    ]);
                }


                $videoId =
                    $segments[1]
                    ?? null;


                if (
                    ! is_string($videoId)
                    || ! ctype_digit(
                        $videoId
                    )
                ) {
                    throw ValidationException::withMessages([
                        'url' =>
                            'El enlace del VOD de Twitch no es válido.',
                    ]);
                }


                return [
                    'provider' =>
                        EventMedia::PROVIDER_TWITCH,

                    'external_id' =>
                        $videoId,

                    'url' =>
                        'https://www.twitch.tv/videos/'
                        . $videoId,
                ];
            }
        }


        /*
        |--------------------------------------------------------------------------
        | Proveedor no soportado
        |--------------------------------------------------------------------------
        */

        throw ValidationException::withMessages([
            'url' =>
                'Solo se admiten enlaces de YouTube o Twitch.',
        ]);
    }

    private function canModerateEventMedia(
        Event $event,
        ?User $user,
    ): bool {

        if (! $user) {
            return false;
        }

        $event->loadMissing('eventStatus');

        if (strtoupper(trim((string) $event->eventStatus?->name)) !== 'FINALIZADO') {
            return false;
        }

        return
            $user->hasRole('admin')
            || $user->can(
                'update',
                $event
            );
    }


    private function canDeleteEventMedia(
        EventMedia $eventMedia,
        ?User $user,
    ): bool {

        if (! $user) {
            return false;
        }

        $eventMedia->loadMissing('event.eventStatus');

        if (strtoupper(trim((string) $eventMedia->event?->eventStatus?->name)) !== 'FINALIZADO') {
            return false;
        }

        /*
        * Propietario.
        */

        if (
            (int) $eventMedia->user_id
            === (int) $user->id
        ) {
            return true;
        }


        /*
        * Moderador.
        */

        $eventMedia->loadMissing('event.activity');

        return $this
            ->canModerateEventMedia(
                $eventMedia->event,
                $user
            );
    }

    private function canManageOrbat(
        ?User $user,
        Event $event,
    ): bool {
        return ActivityTypeAccess::can(
            $user,
            'event-orbat',
            'manage',
            $event->activity?->activity_type_id,
        );
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
    private function setVisibleOccupiedSlotsCount(
        iterable $events
    ): void {
        foreach ($events as $event) {
            $event->ensureOrbatSlotKeys();

            /*
            |--------------------------------------------------------------------------
            | Slot keys visibles del ORBAT
            |--------------------------------------------------------------------------
            */

            $visibleSlotKeys = collect(
                $event->orbat['groups'] ?? []
            )
                ->filter(
                    fn (array $group): bool =>
                        (bool) (
                            $group['visible']
                            ?? true
                        )
                )
                ->flatMap(
                    fn (array $group) =>
                        collect(
                            $group['slots']
                            ?? []
                        )
                            ->filter(
                                fn (array $slot): bool =>
                                    (bool) (
                                        $slot['visible']
                                        ?? true
                                    )
                            )
                            ->pluck('slot_key')
                )
                ->filter()
                ->map(
                    fn ($slotKey): string =>
                        (string) $slotKey
                )
                ->unique()
                ->values();


            /*
            |--------------------------------------------------------------------------
            | Slots visibles realmente ocupados
            |--------------------------------------------------------------------------
            */

            $visibleOccupiedSlotsCount =
                $event->slots
                    ->filter(
                        fn (EventSlot $slot): bool =>
                            $visibleSlotKeys->contains(
                                (string) $slot->slot_key
                            )
                            && (
                                $slot->user_id !== null
                                || $slot->ally_id !== null
                            )
                    )
                    ->pluck('slot_key')
                    ->map(
                        fn ($slotKey): string =>
                            (string) $slotKey
                    )
                    ->unique()
                    ->count();


            /*
            |--------------------------------------------------------------------------
            | Atributo temporal para la tarjeta
            |--------------------------------------------------------------------------
            */

            $event->setAttribute(
                'visible_occupied_slots_count',
                $visibleOccupiedSlotsCount
            );
        }
    }
}
