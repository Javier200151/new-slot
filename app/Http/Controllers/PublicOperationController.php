<?php

namespace App\Http\Controllers;

use App\Models\Addon;
use App\Models\Faction;
use App\Models\Operation;
use App\Models\SlotType;
use Filament\Forms\Components\RichEditor\RichContentRenderer;
use Illuminate\Support\HtmlString;
use Illuminate\View\View;
use App\Models\Platform;
use App\Models\User;
use Illuminate\Http\Request;

class PublicOperationController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
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

            'date_from' => [
                'nullable',
                'date_format:Y-m-d',
            ],

            'date_to' => [
                'nullable',
                'date_format:Y-m-d',
                'after_or_equal:date_from',
            ],
        ]);


        $selectedPlatformId = isset(
            $filters['platform']
        )
            ? (int) $filters['platform']
            : null;


        $selectedEditorId = isset(
            $filters['editor']
        )
            ? (int) $filters['editor']
            : null;


        $selectedDateFrom =
            $filters['date_from']
            ?? null;


        $selectedDateTo =
            $filters['date_to']
            ?? null;


        $operations = Operation::query()

            ->with([
                'operationType',
                'operationStatus',
                'campaign',
                'platform',
                'map',
                'period',

                'editor.status',
                'editor.mainSqaGroup',
            ])

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
            | Fecha de eventos
            |--------------------------------------------------------------------------
            |
            | Un operativo no tiene una única fecha.
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
            )

            ->orderBy('name')

            ->get();


        /*
        |--------------------------------------------------------------------------
        | Plataformas disponibles
        |--------------------------------------------------------------------------
        |
        | Solo plataformas utilizadas realmente por algún operativo.
        |
        */

        $platformIds = Operation::query()
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
        | Solo usuarios que figuren como editor de al menos un operativo.
        |
        */

        $editorIds = Operation::query()
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


        $hasFilters =
            $selectedPlatformId
            || $selectedEditorId
            || $selectedDateFrom
            || $selectedDateTo;


        return view(
            'operations.index',
            compact(
                'operations',
                'platforms',
                'editors',
                'selectedPlatformId',
                'selectedEditorId',
                'selectedDateFrom',
                'selectedDateTo',
                'hasFilters',
            )
        );
    }

    public function show(Operation $operation): View
    {
        $operation->load([
            'operationType',
            'operationStatus',
            'campaign',
            'period',
            'platform',
            'map',
            'days',

            'editor.status',
            'editor.mainSqaGroup',

            'enemyFactions.army',
            'enemyFactions.side',
        ]);
        /*
        |--------------------------------------------------------------------------
        | Eventos de este operativo
        |--------------------------------------------------------------------------
        |
        | Recuperamos todo el historial.
        |
        | No limitamos por año ni por estado:
        | si se jugó hace tres años seguirá apareciendo.
        |
        */

        $operationEvents = $operation
            ->events()
            ->with([
                'eventStatus',
                'eventResult',
            ])
            ->orderByDesc('date')
            ->get();


        /*
        |--------------------------------------------------------------------------
        | Próximos eventos
        |--------------------------------------------------------------------------
        */

        $upcomingEvents = $operationEvents
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

        $pastEvents = $operationEvents
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
        | Aquí usamos directamente el ORBAT plantilla del operativo.
        | No existen asignaciones de usuarios porque no estamos viendo un evento.
        |
        */

        $groups = collect(
            $operation->orbat['groups'] ?? []
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
            $operation->description ?? [];

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
                            'title' =>
                                $section['title']
                                ?? 'Descripción',

                            'content' =>
                                filled($content)
                                    ? new HtmlString(
                                        RichContentRenderer::make(
                                            $content
                                        )->toHtml()
                                    )
                                    : new HtmlString(''),

                            'image' =>
                                $section['image']
                                ?? null,

                            'image_position' =>
                                $position,

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
            $operation->radio['networks']
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
                $operation
                    ->addons['addon_ids']
                    ?? []
            )
            ->orderByDesc('mandatory')
            ->orderBy('name')
            ->get();

        return view(
            'operations.show',
            compact(
                'operation',
                'visibleOrbatGroups',
                'descriptionSections',
                'radioNetworks',
                'addons',
                'operationEvents',
                'upcomingEvents',
                'pastEvents',
            )
        );
    }
}