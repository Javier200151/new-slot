<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Faction;
use App\Models\Activity;
use App\Models\SlotType;
use Illuminate\View\View;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\Request;
use App\Models\Campaign;
use App\Models\GameMap;
use App\Models\ActivityDay;
use App\Models\ActivityStatus;
use App\Models\ActivityType;
use App\Models\Period;
use App\Support\BriefingMarkup;

class PublicActivityController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([

            'q' => [
                'nullable',
                'string',
                'max:100',
            ],

            'platform' => [
                'nullable',
                'integer',
                'exists:platforms,id',
            ],

            'editor' => [
                'nullable',
                'integer',
                'exists:users,id',
            ],

            'type' => [
                'nullable',
                'integer',
                'exists:activity_types,id',
            ],

            'status' => [
                'nullable',
                'integer',
                'exists:activity_statuses,id',
            ],

            'map' => [
                'nullable',
                'integer',
                'exists:maps,id',
            ],

            'period' => [
                'nullable',
                'integer',
                'exists:periods,id',
            ],

            'campaign' => [
                'nullable',
                'integer',
                'exists:campaign,id',
            ],

            'faction' => [
                'nullable',
                'integer',
                'exists:factions,id',
            ],

            'day' => [
                'nullable',
                'integer',
                'exists:activity_days,id',
            ],

            'day_or_night' => [
                'nullable',
                'in:day,night,both',
            ],

            'ocap' => [
                'nullable',
                'in:0,1',
            ],

            'respawn' => [
                'nullable',
                'in:0,1',
            ],

            'jip' => [
                'nullable',
                'in:0,1',
            ],

            'multiclans' => [
                'nullable',
                'in:0,1',
            ],

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],

            'sort' => [
                'nullable',
                'in:published_desc,published_asc,name_asc,name_desc',
            ],
        ]);

        $search =
            trim(
                (string) (
                    $filters['q']
                    ?? ''
                )
            );

        $selectedPlatformId =
            isset($filters['platform'])
                ? (int) $filters['platform']
                : null;

        $selectedEditorId =
            isset($filters['editor'])
                ? (int) $filters['editor']
                : null;

        $selectedTypeId =
            isset($filters['type'])
                ? (int) $filters['type']
                : null;

        $selectedStatusId =
            isset($filters['status'])
                ? (int) $filters['status']
                : null;

        $selectedMapId =
            isset($filters['map'])
                ? (int) $filters['map']
                : null;

        $selectedPeriodId =
            isset($filters['period'])
                ? (int) $filters['period']
                : null;

        $selectedCampaignId =
            isset($filters['campaign'])
                ? (int) $filters['campaign']
                : null;

        $selectedFactionId =
            isset($filters['faction'])
                ? (int) $filters['faction']
                : null;

        $selectedDayId =
            isset($filters['day'])
                ? (int) $filters['day']
                : null;

        $selectedDayOrNight =
            $filters['day_or_night']
            ?? null;

        $selectedOcap =
            $filters['ocap']
            ?? null;

        $selectedRespawn =
            $filters['respawn']
            ?? null;

        $selectedJip =
            $filters['jip']
            ?? null;

        $selectedMulticlans =
            $filters['multiclans']
            ?? null;

        $selectedDateFrom =
            $filters['date_from']
            ?? null;

        $selectedDateTo =
            $filters['date_to']
            ?? null;

        $selectedSort = $filters['sort'] ?? 'published_desc';


        $activitiesQuery = Activity::query()

            ->with([
                'activityType',
                'activityStatus',
                'campaign',
                'platform',
                'map',
                'period',

                'editor.status',
                'editor.mainSqaGroup',
                'editorAlly',
                'metopa',
            ])

            /*
            |--------------------------------------------------------------------------
            | Buscador general
            |--------------------------------------------------------------------------
            */

            ->when(
                filled($search),

                function ($query) use ($search): void {

                    $like =
                        '%' . $search . '%';

                    $query->where(
                        function ($searchQuery) use ($like): void {

                            /*
                            * Nombre / PBO.
                            */

                            $searchQuery
                                ->where(
                                    'name',
                                    'like',
                                    $like
                                )
                                ->orWhere(
                                    'pbo',
                                    'like',
                                    $like
                                );


                            /*
                            * Editor.
                            */

                            $searchQuery->orWhereHas(
                                'editor',

                                fn ($query) =>
                                    $query->where(
                                        'nick',
                                        'like',
                                        $like
                                    )
                            );

                            $searchQuery->orWhereHas(
                                'editorAlly',
                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Plataforma.
                            */

                            $searchQuery->orWhereHas(
                                'platform',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Mapa.
                            */

                            $searchQuery->orWhereHas(
                                'map',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Periodo.
                            */

                            $searchQuery->orWhereHas(
                                'period',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Campaña.
                            */

                            $searchQuery->orWhereHas(
                                'campaign',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Tipo.
                            */

                            $searchQuery->orWhereHas(
                                'activityType',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );


                            /*
                            * Facción enemiga.
                            */

                            $searchQuery->orWhereHas(
                                'enemyFactions',

                                fn ($query) =>
                                    $query->where(
                                        'name',
                                        'like',
                                        $like
                                    )
                            );
                        }
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Plataforma
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedPlatformId,
                fn ($query) => $query->where(
                    'platform_id',
                    $selectedPlatformId
                )
            )

            /*
            |--------------------------------------------------------------------------
            | Editor
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedEditorId,
                fn ($query) => $query->where(
                    'editor_id',
                    $selectedEditorId
                )
            )

            /*
            |--------------------------------------------------------------------------
            | Tipo
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedTypeId,

                fn ($query) =>
                    $query->where(
                        'activity_type_id',
                        $selectedTypeId
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Estado
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedStatusId,

                fn ($query) =>
                    $query->where(
                        'activity_status_id',
                        $selectedStatusId
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Mapa
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedMapId,

                fn ($query) =>
                    $query->where(
                        'map_id',
                        $selectedMapId
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Periodo
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedPeriodId,

                fn ($query) =>
                    $query->where(
                        'period_id',
                        $selectedPeriodId
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Campaña
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedCampaignId,

                fn ($query) =>
                    $query->where(
                        'campaign_id',
                        $selectedCampaignId
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Facción enemiga
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedFactionId,

                fn ($query) =>
                    $query->whereHas(
                        'enemyFactions',

                        fn ($factionQuery) =>
                            $factionQuery->whereKey(
                                $selectedFactionId
                            )
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Día habitual
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedDayId,

                fn ($query) =>
                    $query->whereHas(
                        'days',

                        fn ($dayQuery) =>
                            $dayQuery->whereKey(
                                $selectedDayId
                            )
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Día / Noche
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedDayOrNight,

                fn ($query) =>
                    $query->where(
                        'day_or_night',
                        $selectedDayOrNight
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | OCAP
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedOcap !== null,

                fn ($query) =>
                    $query->where(
                        'ocap',
                        (bool) (int) $selectedOcap
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | Respawn
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedRespawn !== null,

                fn ($query) =>
                    $query->where(
                        'respawn',
                        (bool) (int) $selectedRespawn
                    )
            )


            /*
            |--------------------------------------------------------------------------
            | JIP
            |--------------------------------------------------------------------------
            */

            ->when(
                $selectedJip !== null,

                fn ($query) =>
                    $query->where(
                        'jip',
                        (bool) (int) $selectedJip
                    )
            )

            /*
            |--------------------------------------------------------------------------
            | Multiclanes
            |--------------------------------------------------------------------------
            |
            | La marca multiclanes pertenece al evento, no al actividad. Un actividad
            | se considera multiclan si al menos uno de sus eventos está marcado como
            | tal. Al filtrar por "No" mostramos las actividades que no tienen ningún
            | evento multiclan.
            |
            */

            ->when(
                $selectedMulticlans !== null,

                function ($query) use ($selectedMulticlans): void {
                    if ($selectedMulticlans === '1') {
                        $query->whereHas(
                            'events',
                            fn ($eventQuery) => $eventQuery->where('multiclans', true)
                        );

                        return;
                    }

                    $query->whereDoesntHave(
                        'events',
                        fn ($eventQuery) => $eventQuery->where('multiclans', true)
                    );
                }
            )

            /*
            |--------------------------------------------------------------------------
            | Fecha de eventos
            |--------------------------------------------------------------------------
            |
            | Un actividad no tiene una única fecha.
            | Puede haberse jugado varias veces.
            |
            | Por tanto filtramos por los eventos asociados.
            |
            */

            ->when(
                $selectedDateFrom || $selectedDateTo,

                function ($query) use (
                    $selectedDateFrom,
                    $selectedDateTo
                ): void {

                    $query->whereHas(
                        'events',

                        function ($eventQuery) use (
                            $selectedDateFrom,
                            $selectedDateTo
                        ): void {

                            if ($selectedDateFrom) {
                                $eventQuery->whereDate(
                                    'date',
                                    '>=',
                                    $selectedDateFrom
                                );
                            }

                            if ($selectedDateTo) {
                                $eventQuery->whereDate(
                                    'date',
                                    '<=',
                                    $selectedDateTo
                                );
                            }
                        }
                    );
                }
            );

        match ($selectedSort) {
            'published_asc' => $activitiesQuery
                ->orderBy('created_at')
                ->orderBy('id'),
            'name_asc' => $activitiesQuery
                ->orderBy('name')
                ->orderBy('id'),
            'name_desc' => $activitiesQuery
                ->orderByDesc('name')
                ->orderByDesc('id'),
            default => $activitiesQuery
                ->orderByDesc('created_at')
                ->orderByDesc('id'),
        };

        $activities = $activitiesQuery->get();

        /*
        |--------------------------------------------------------------------------
        | Agrupar actividades por campaña para la vista pública
        |--------------------------------------------------------------------------
        |
        | La consulta anterior ya contiene exclusivamente las actividades que
        | cumplen los filtros actuales.
        |
        | Aquí NO volvemos a consultar campañas ni actividades.
        |
        | Esto permite que:
        |
        | - las actividades sin campaña aparezcan normalmente;
        | - cada campaña aparezca una sola vez;
        | - sus actividades aparezcan dentro de ella;
        | - los filtros también afecten a las actividades de las campañas;
        | - una campaña sin actividades después del filtrado no aparezca.
        |
        */

        $activitiesByCampaign = $activities
            ->whereNotNull('campaign_id')
            ->groupBy('campaign_id');


        $renderedCampaigns = [];


        /*
        * Esta será la colección que recorrerá después
        * activities/index.blade.php.
        *
        * Cada elemento tendrá uno de estos formatos:
        *
        * [
        *     'type' => 'activity',
        *     'activity' => $activity,
        * ]
        *
        * o:
        *
        * [
        *     'type' => 'campaign',
        *     'campaign' => $campaign,
        * ]
        */

        $activityItems = collect();


        foreach ($activities as $activity) {

            /*
            * Actividad independiente.
            */

            if (
                ! $activity->campaign_id
                || ! $activity->campaign
            ) {
                $activityItems->push([
                    'type' => 'activity',
                    'activity' => $activity,
                ]);

                continue;
            }


            /*
            * Actividad perteneciente a campaña.
            */

            $campaignId =
                (int) $activity->campaign_id;


            /*
            * Si ya hemos añadido esta campaña,
            * no volvemos a crear otra tarjeta.
            *
            * Sus actividades ya estarán dentro
            * de campaign->activities.
            */

            if (
                isset(
                    $renderedCampaigns[
                        $campaignId
                    ]
                )
            ) {
                continue;
            }


            $campaign =
                $activity->campaign;


            /*
            * Sobrescribimos para esta vista la
            * relación activities de la campaña.
            *
            * Así contiene únicamente las actividades
            * que han sobrevivido a los filtros
            * aplicados anteriormente.
            */

            $campaign->setRelation(
                'activities',

                $activitiesByCampaign
                    ->get(
                        $campaignId,
                        collect()
                    )
                    ->values()
            );


            $activityItems->push([
                'type' => 'campaign',
                'campaign' => $campaign,
            ]);


            $renderedCampaigns[
                $campaignId
            ] = true;
        }

        /*
        |--------------------------------------------------------------------------
        | Plataformas disponibles
        |--------------------------------------------------------------------------
        |
        | Solo plataformas utilizadas realmente por algún actividad.
        |
        */

        $platformIds = Activity::query()
            ->whereNotNull('platform_id')
            ->distinct()
            ->pluck('platform_id');


        $platforms = Platform::query()
            ->whereIn('id', $platformIds)
            ->orderBy('name')
            ->get([
                'id',
                'name',
                'image',
            ]);


        /*
        |--------------------------------------------------------------------------
        | Editores disponibles
        |--------------------------------------------------------------------------
        |
        | Solo usuarios que figuren como editor de al menos un actividad.
        |
        */

        $editorIds = Activity::query()
            ->whereNotNull('editor_id')
            ->distinct()
            ->pluck('editor_id');


        $editors = User::query()
            ->whereIn('id', $editorIds)
            ->orderBy('nick')
            ->get([
                'id',
                'nick',
            ]);

        $activityTypes =
            ActivityType::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $activityStatuses =
            ActivityStatus::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $maps =
            GameMap::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $periods =
            Period::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $campaigns =
            Campaign::query()
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $factions =
            Faction::query()
                ->whereHas('enemyActivities')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $activityDays =
            ActivityDay::query()
                ->whereHas('activities')
                ->orderBy('name')
                ->get([
                    'id',
                    'name',
                ]);


        $hasFilters =
            filled($search)
            || $selectedPlatformId
            || $selectedEditorId
            || $selectedTypeId
            || $selectedStatusId
            || $selectedMapId
            || $selectedPeriodId
            || $selectedCampaignId
            || $selectedFactionId
            || $selectedDayId
            || $selectedDayOrNight
            || $selectedOcap !== null
            || $selectedRespawn !== null
            || $selectedJip !== null
            || $selectedMulticlans !== null
            || $selectedDateFrom
            || $selectedDateTo;


        return view(
            'activities.index',
            compact(
                'activities',
                'activityItems',

                'platforms',
                'editors',
                'activityTypes',
                'activityStatuses',
                'maps',
                'periods',
                'campaigns',
                'factions',
                'activityDays',

                'search',

                'selectedPlatformId',
                'selectedEditorId',
                'selectedTypeId',
                'selectedStatusId',
                'selectedMapId',
                'selectedPeriodId',
                'selectedCampaignId',
                'selectedFactionId',
                'selectedDayId',
                'selectedDayOrNight',
                'selectedOcap',
                'selectedRespawn',
                'selectedJip',
                'selectedMulticlans',
                'selectedDateFrom',
                'selectedDateTo',
                'selectedSort',

                'hasFilters',
            )
        );
    }

    public function show(Activity $activity): View
    {
        $activity->load([
            'activityType',
            'activityStatus',
            'campaign',
            'period',
            'platform',
            'map',
            'days',

            'editor.status',
            'editor.mainSqaGroup',
            'editorAlly',
            'metopa',

            'enemyFactions.army.country',
            'enemyFactions.side',
        ]);
        /*
        |--------------------------------------------------------------------------
        | Eventos de esta actividad
        |--------------------------------------------------------------------------
        |
        | Recuperamos todo el historial.
        |
        | No limitamos por año ni por estado:
        | si se jugó hace tres años seguirá apareciendo.
        |
        */

        $activityEvents = $activity
            ->events()
            ->with([
                'eventStatus',
                'eventResult',
                'slots.ally:id,name,image,url',
            ])
            ->whereDoesntHave(
                'eventStatus',
                fn ($query) => $query->where(
                    'name',
                    'BORRADOR'
                )
            )
            ->orderByDesc('date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Próximos eventos
        |--------------------------------------------------------------------------
        */

        $upcomingEvents = $activityEvents
            ->filter(
                fn ($event): bool =>
                    $event->date !== null
                    && $event->date->isFuture()
            )
            ->sortBy('date')
            ->values();


        /*
        |--------------------------------------------------------------------------
        | Histórico
        |--------------------------------------------------------------------------
        */

        $pastEvents = $activityEvents
            ->filter(
                fn ($event): bool =>
                    $event->date !== null
                    && ! $event->date->isFuture()
            )
            ->sortByDesc('date')
            ->values();

        /*
        |--------------------------------------------------------------------------
        | ORBAT
        |--------------------------------------------------------------------------
        |
        | Aquí usamos directamente el ORBAT plantilla del actividad.
        | No existen asignaciones de usuarios porque no estamos viendo un evento.
        |
        */

        $groups = collect(
            $activity->orbat['groups'] ?? []
        )
            ->filter(
                fn (array $group): bool =>
                    (bool) ($group['visible'] ?? true)
            );

        $factions = Faction::query()
            ->with([
                'army',
                'side',
            ])
            ->whereIn(
                'id',
                $groups
                    ->pluck('faction_id')
                    ->filter()
                    ->unique()
            )
            ->get()
            ->keyBy('id');

        $slotTypeIds = $groups
            ->flatMap(
                fn (array $group): array =>
                    $group['slots'] ?? []
            )
            ->pluck('slot_type_id')
            ->filter()
            ->unique();

        $slotTypes = SlotType::query()
            ->whereIn('id', $slotTypeIds)
            ->get()
            ->keyBy('id');

        $visibleOrbatGroups = $groups
            ->map(
                function (
                    array $group
                ) use (
                    $factions,
                    $slotTypes
                ): array {

                    $group['faction'] =
                        $factions->get(
                            (int) (
                                $group['faction_id']
                                ?? 0
                            )
                        );

                    $group['slots'] = collect(
                        $group['slots'] ?? []
                    )
                        ->filter(
                            fn (array $slot): bool =>
                                (bool) (
                                    $slot['visible']
                                    ?? true
                                )
                        )
                        ->map(
                            function (
                                array $slot
                            ) use (
                                $slotTypes
                            ): array {

                                $slot['slot_type'] =
                                    $slotTypes->get(
                                        (int) (
                                            $slot['slot_type_id']
                                            ?? 0
                                        )
                                    );

                                return $slot;
                            }
                        )
                        ->values();

                    return $group;
                }
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Briefing
        |--------------------------------------------------------------------------
        */

        $description =
            $activity->description ?? [];

        $descriptionSections = collect(
            $description['sections'] ?? []
        );

        /*
         * Compatibilidad con el formato antiguo.
         */
        if (
            $descriptionSections->isEmpty()
            && filled(
                $description['content']
                ?? null
            )
        ) {
            $descriptionSections = collect([
                [
                    'title' => 'Descripción',
                    'content' =>
                        $description['content'],
                ],
            ]);
        }

        $descriptionSections =
            $descriptionSections
                ->map(
                    function (
                        array $section
                    ): array {

                        $position =
                            $section[
                                'image_position'
                            ]
                            ?? 'left';

                        if (
                            ! in_array(
                                $position,
                                [
                                    'left',
                                    'right',
                                    'top',
                                    'bottom',
                                ],
                                true
                            )
                        ) {
                            $position = 'left';
                        }

                        $alignment =
                            $section['image_alignment']
                            ?? 'left';

                        if (
                            ! in_array(
                                $alignment,
                                [
                                    'left',
                                    'center',
                                    'right',
                                ],
                                true
                            )
                        ) {
                            $alignment = 'left';
                        }

                        $width = (string) (
                            $section[
                                'image_width'
                            ]
                            ?? '40'
                        );

                        if (
                            ! in_array(
                                $width,
                                [
                                    '33',
                                    '40',
                                    '50',
                                    '66',
                                    '100',
                                ],
                                true
                            )
                        ) {
                            $width = '40';
                        }

                        if (
                            $width === '100'
                            && in_array(
                                $position,
                                [
                                    'left',
                                    'right',
                                ],
                                true
                            )
                        ) {
                            $position = 'top';
                        }

                        $content =
                            $section[
                                'content'
                            ]
                            ?? '';

                        return [
                            'title' => BriefingMarkup::render(
                                $section['title']
                                ?? 'Descripción'
                            ),

                            'content' => BriefingMarkup::render(
                                $content
                            ),

                            'image' => BriefingMarkup::imageUrl(
                                $section['image']
                                ?? null
                            ),

                            'image_position' =>
                                $position,

                            'image_alignment' =>
                                $alignment,

                            'image_width' =>
                                $width,

                            'image_caption' =>
                                $section[
                                    'image_caption'
                                ]
                                ?? null,
                        ];
                    }
                );

        /*
        |--------------------------------------------------------------------------
        | Comunicaciones
        |--------------------------------------------------------------------------
        */

        $radioNetworks = collect(
            $activity->radio['networks']
            ?? []
        )
            ->filter(
                fn (array $network): bool =>
                    (bool) (
                        $network['visible']
                        ?? true
                    )
            )
            ->values();

        /*
        |--------------------------------------------------------------------------
        | Addons
        |--------------------------------------------------------------------------
        */

        $addons = Addon::query()
            ->whereIn(
                'id',
                $activity
                    ->addons['addon_ids']
                    ?? []
            )
            ->orderByDesc('mandatory')
            ->orderBy('name')
            ->get();

        return view(
            'activities.show',
            compact(
                'activity',
                'visibleOrbatGroups',
                'descriptionSections',
                'radioNetworks',
                'addons',
                'activityEvents',
                'upcomingEvents',
                'pastEvents',
            )
        );
    }
}
