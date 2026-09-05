<?php

namespace App\Filament\Resources\Activities\Pages;

use Illuminate\Validation\ValidationException;
use App\Filament\Resources\Activities\ActivityResource;
use App\Models\Addon;
use App\Models\AddonPreset;
use App\Models\Faction;
use App\Models\RadioModel;
use App\Models\SlotType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\CheckboxList;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\ToggleButtons;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;
use App\Models\Country;
use App\Models\Army;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Support\Enums\VerticalAlignment;
use App\Services\AuditLogger;
use App\Services\ActivityBriefingSqfExporter;
use App\Models\ActivityStatus;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityEditorSelection;
use App\Support\FactionOptionLabel;
use App\Support\ActivityTypeConfiguration;
use App\Support\SlotQuickSelection;
use App\Support\BriefingMarkup;

class EditActivity extends EditRecord
{
    protected static string $resource = ActivityResource::class;
    
    protected array $auditDaysBefore = [];

    protected array $auditEnemyFactionsBefore = [];

    private ?array $orbatFactionOptionsCache = null;

    /**
     * Capacidades de los modelos de radio durante la petición Livewire actual.
     * Evita repetir la misma consulta por cada campo Canal/Bloque/Frecuencia.
     *
     * @var array<int, array{channel: bool, block: bool, frequency: bool}>
     */
    protected static array $radioModelCapabilitiesCache = [];

    protected function mutateFormDataBeforeFill(array $data): array
    {
        return ActivityEditorSelection::addChoiceToFormData($data);
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        $data = ActivityEditorSelection::resolveChoice($data);
        $data = ActivityTypeConfiguration::normalizeActivityData($data);

        $targetOperationTypeId =
            $data['activity_type_id'] ?? null;

        if (! ActivityTypeAccess::can(
            auth()->user(),
            'activities',
            'update',
            $targetOperationTypeId,
        )) {
            throw ValidationException::withMessages([
                'data.activity_type_id' =>
                    'No tienes permiso para modificar actividades de este tipo.',
            ]);
        }

        /*
        |--------------------------------------------------------------------------
        | Estado al que queremos pasar el actividad
        |--------------------------------------------------------------------------
        */

        $targetStatus =
            ActivityStatus::query()
                ->whereKey(
                    $data['activity_status_id']
                    ?? null
                )
                ->value('name');


        /*
        |--------------------------------------------------------------------------
        | Solo necesitamos comprobarlo al pasar a BORRADOR
        |--------------------------------------------------------------------------
        */

        if ($targetStatus !== 'BORRADOR') {
            return $data;
        }


        /*
        |--------------------------------------------------------------------------
        | Comprobar eventos publicados
        |--------------------------------------------------------------------------
        |
        | Un actividad BORRADOR puede tener eventos BORRADOR preparados.
        |
        | Pero si ya existe un evento ACTIVO, FINALIZADO o cualquier otro
        | estado público, el actividad no puede volver a BORRADOR.
        |
        */

        $hasPublishedEvents =
            $this->record
                ->events()
                ->whereHas(
                    'eventStatus',
                    fn ($query) =>
                        $query->where(
                            'name',
                            '!=',
                            'BORRADOR'
                        )
                )
                ->exists();

        if ($hasPublishedEvents) {
            throw ValidationException::withMessages([
                'data.activity_status_id' =>
                    'No puedes pasar esta actividad a BORRADOR '
                    . 'porque tiene uno o más eventos publicados. '
                    . 'Los eventos deben permanecer en BORRADOR '
                    . 'antes de poder cambiar el estado de la actividad.',
            ]);
        }

        return $data;
    }

    protected function beforeSave(): void
    {
        $this->auditDaysBefore =
            $this->record
                ->days()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();

        $this->auditEnemyFactionsBefore =
            $this->record
                ->enemyFactions()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();
    }

    protected function afterSave(): void
    {
        $this->record->loadMissing('activityType');

        if (! ($this->record->activityType?->usesEnemyFactions() ?? false)) {
            $this->record->enemyFactions()->detach();
        }

        $daysAfter =
            $this->record
                ->days()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();

        if (
            $this->auditDaysBefore
            !== $daysAfter
        ) {
            app(AuditLogger::class)
                ->change(
                    subject: $this->record,

                    event: 'activity_days_updated',

                    old: [
                        'days' =>
                            $this->auditDaysBefore,
                    ],

                    new: [
                        'days' =>
                            $daysAfter,
                    ],

                    properties: [
                        'relation' => 'days',

                        'table' =>
                            'activity_day_assignments',
                    ],
                );
        }


        $enemyFactionsAfter =
            $this->record
                ->enemyFactions()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();

        if (
            $this->auditEnemyFactionsBefore
            !== $enemyFactionsAfter
        ) {
            app(AuditLogger::class)
                ->change(
                    subject: $this->record,

                    event:
                        'activity_enemy_factions_updated',

                    old: [
                        'enemy_factions' =>
                            $this
                                ->auditEnemyFactionsBefore,
                    ],

                    new: [
                        'enemy_factions' =>
                            $enemyFactionsAfter,
                    ],

                    properties: [
                        'relation' =>
                            'enemyFactions',

                        'table' =>
                            'activity_enemy_faction',
                    ],
                );
        }
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    private function canDownloadBriefingSqf(): bool
    {
        $this->record->loadMissing([
            'activityType',
            'platform',
        ]);

        $activityType = strtoupper(
            Str::ascii(
                trim(
                    (string) (
                        $this->record->activityType?->name
                        ?? ''
                    )
                )
            )
        );

        $platform = strtoupper(
            preg_replace(
                '/[^A-Za-z0-9]+/',
                '',
                Str::ascii(
                    trim(
                        (string) (
                            $this->record->platform?->name
                            ?? ''
                        )
                    )
                )
            )
            ?? ''
        );

        return in_array(
            $activityType,
            [
                'OPERACION',
                'OPERATIVO',
            ],
            true,
        )
            && $platform === 'ARMA3';
    }

    private function detectBriefingSqfSide(): string
    {
        $groups = collect(
            $this->record->orbat['groups'] ?? []
        )
            ->filter(
                fn (mixed $group): bool =>
                    is_array($group)
                    && (bool) ($group['visible'] ?? true)
                    && (int) ($group['faction_id'] ?? 0) > 0
            )
            ->values();

        if ($groups->isEmpty()) {
            return 'WEST';
        }

        $factionIds = $groups
            ->pluck('faction_id')
            ->map(fn (mixed $id): int => (int) $id)
            ->filter()
            ->unique()
            ->values();

        $factions = Faction::query()
            ->with('side:id,name,description')
            ->whereIn('id', $factionIds)
            ->get()
            ->keyBy('id');

        foreach ($groups as $group) {
            $faction = $factions->get(
                (int) ($group['faction_id'] ?? 0)
            );

            if (! $faction?->side) {
                continue;
            }

            $description = trim(
                (string) ($faction->side->description ?? '')
            );

            if ($description !== '') {
                $candidate = strtoupper(
                    trim(explode(',', $description, 2)[0])
                );

                if (preg_match('/^[A-Z_][A-Z0-9_]*$/', $candidate)) {
                    return $candidate;
                }
            }

            $sideName = strtoupper(
                Str::ascii(
                    trim((string) $faction->side->name)
                )
            );

            $candidate = match ($sideName) {
                'BLUFOR' => 'WEST',
                'OPFOR' => 'EAST',
                'INDEPENDIENTE',
                'INDEPENDENT',
                'INDFOR' => 'RESISTANCE',
                'CIVIL',
                'CIVILIAN' => 'CIVILIAN',
                default => null,
            };

            if ($candidate !== null) {
                return $candidate;
            }
        }

        return 'WEST';
    }

    private function orbatFactionOptions(): array
    {
        if ($this->orbatFactionOptionsCache !== null) {
            return $this->orbatFactionOptionsCache;
        }

        return $this->orbatFactionOptionsCache = Faction::query()
            ->with([
                'side',
                'army.country',
            ])
            ->orderBy('name')
            ->get()
            ->mapWithKeys(
                fn (Faction $faction): array => [
                    $faction->id => FactionOptionLabel::make($faction),
                ]
            )
            ->all();
    }

    private static function slotPickerSchema(): array
    {
        $groups = SlotQuickSelection::pickerGroups();
        $pickerColumns = SlotQuickSelection::pickerColumns();
        $pickerImages = SlotQuickSelection::pickerImages();

        $fieldNames = collect(array_keys($groups))
            ->mapWithKeys(
                fn (string $slotTypeName): array => [
                    $slotTypeName => SlotQuickSelection::pickerFieldName(
                        $slotTypeName
                    ),
                ]
            )
            ->all();

        $normalizeSearch = static function (mixed $value): string {
            return Str::lower(
                Str::ascii(
                    trim((string) ($value ?? ''))
                )
            );
        };

        $buildSection = function (
            array $options,
            string $slotTypeName
        ) use (
            $fieldNames,
            $normalizeSearch,
            $pickerImages
        ): Section {
            $fieldName = $fieldNames[$slotTypeName];

            $filteredOptions = static function (
                Get $get
            ) use (
                $options,
                $slotTypeName,
                $normalizeSearch
            ): array {
                $query = $normalizeSearch($get('slot_search'));

                if ($query === '') {
                    return $options;
                }

                if (
                    str_contains(
                        $normalizeSearch($slotTypeName),
                        $query
                    )
                ) {
                    return $options;
                }

                return collect($options)
                    ->filter(
                        fn (mixed $label): bool => str_contains(
                            $normalizeSearch($label),
                            $query
                        )
                    )
                    ->all();
            };

            $slotTypeImage = $pickerImages[$slotTypeName] ?? null;

            $heading = filled($slotTypeImage)
                ? new HtmlString(
                    '<span style="display:inline-flex;align-items:center;gap:8px;min-width:0;">'
                    . '<img src="' . e(Storage::disk('public')->url($slotTypeImage)) . '" '
                    . 'alt="" width="24" height="24" '
                    . 'style="display:block;width:24px;height:24px;max-width:24px;max-height:24px;object-fit:contain;flex:0 0 24px;">'
                    . '<span style="min-width:0;">' . e($slotTypeName) . '</span>'
                    . '</span>'
                )
                : $slotTypeName;

            return Section::make($heading)
                ->visible(
                    function (Get $get) use (
                        $filteredOptions
                    ): bool {
                        return $filteredOptions($get) !== [];
                    }
                )
                ->schema([
                    ToggleButtons::make($fieldName)
                        ->hiddenLabel()
                        ->options($filteredOptions)
                        ->columns(1)
                        ->live()
                        ->afterStateUpdated(
                            function (
                                $state,
                                Set $set,
                                $livewire
                            ) use (
                                $fieldName,
                                $fieldNames
                            ): void {
                                if (blank($state)) {
                                    return;
                                }

                                foreach ($fieldNames as $otherFieldName) {
                                    if ($otherFieldName !== $fieldName) {
                                        $set($otherFieldName, null);
                                    }
                                }

                                $set(
                                    'selected_slot_choice',
                                    is_string($state) ? $state : null
                                );

                                /*
                                |----------------------------------------------
                                | Selección inmediata
                                |----------------------------------------------
                                |
                                | Al pulsar una opción se aplica directamente
                                | al slot y se cierra el modal.
                                |
                                */
                                $livewire->callMountedAction();
                            }
                        ),
                ])
                ->compact();
        };

        $columns = collect($pickerColumns)
            ->map(
                fn (array $columnGroups): array => collect($columnGroups)
                    ->map(
                        fn (
                            array $options,
                            string $slotTypeName
                        ): Section => $buildSection(
                            $options,
                            $slotTypeName
                        )
                    )
                    ->values()
                    ->all()
            )
            ->values();

        return [
            TextInput::make('slot_search')
                ->label('Buscar')
                ->placeholder('Buscar tipo o nombre de slot...')
                ->live(debounce: 200)
                ->dehydrated(false)
                ->extraInputAttributes([
                    'autocomplete' => 'off',
                ])
                ->columnSpanFull(),

            Hidden::make('selected_slot_choice')
                ->required(),

            Grid::make([
                'default' => 1,
                'md' => 2,
                'xl' => 4,
            ])
                ->schema(
                    $columns
                        ->map(
                            fn (array $columnSections): Grid =>
                                Grid::make(1)
                                    ->schema($columnSections)
                                    ->columnSpan(1)
                        )
                        ->all()
                )
                ->columnSpanFull(),
        ];
    }

    protected function getHeaderActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->submit(null)
                ->action('save')
                ->label('Guardar')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ]),

            $this->getCancelFormAction()
                ->label('Cancelar')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ]),

            DeleteAction::make()
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ]),

            ForceDeleteAction::make()
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ]),

            RestoreAction::make()
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ]),

            Action::make('downloadBriefingSqf')
                ->label('Descargar briefing.sqf')
                ->icon('heroicon-o-arrow-down-tray')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--secondary',
                ])
                ->visible(
                    fn (): bool =>
                        $this->canDownloadBriefingSqf()
                )
                ->modalHeading('Exportar briefing para Arma 3')
                ->modalDescription(
                    'Indica el bando jugable y la ruta del banner .paa. '
                    . 'El resto del briefing se genera automáticamente '
                    . 'a partir de las secciones guardadas en la actividad.'
                )
                ->modalSubmitActionLabel('Descargar briefing.sqf')
                ->form([
                    TextInput::make('side')
                        ->label('Bando')
                        ->default(
                            fn (): string =>
                                $this->detectBriefingSqfSide()
                        )
                        ->required()
                        ->maxLength(40)
                        ->helperText(
                            'Se intenta detectar desde la facción del ORBAT. '
                            . 'Puedes modificarlo antes de descargar. '
                            . 'Valores habituales: WEST, EAST, RESISTANCE o CIVILIAN.'
                        )
                        ->rules([
                            'regex:/^[A-Za-z_][A-Za-z0-9_]*$/',
                        ]),

                    TextInput::make('banner_path')
                        ->label('Imagen .paa del briefing')
                        ->placeholder('images\STILLHERE_banner.paa')
                        ->maxLength(255)
                        ->helperText(
                            'Opcional. Ruta dentro de la misión. '
                            . 'Ejemplo: images\STILLHERE_banner.paa'
                        ),
                ])
                ->action(
                    function (
                        array $data,
                        ActivityBriefingSqfExporter $exporter,
                    ) {
                        $sqf = $exporter->export(
                            $this->record,
                            (string) $data['side'],
                            (string) ($data['banner_path'] ?? ''),
                        );

                        return response()->streamDownload(
                            static function () use ($sqf): void {
                                echo $sqf;
                            },
                            'briefing.sqf',
                            [
                                'Content-Type' =>
                                    'text/plain; charset=UTF-8',
                                'Cache-Control' =>
                                    'no-store, no-cache, must-revalidate',
                            ],
                        );
                    }
                ),

            Action::make('editDescription')
            ->label('Editar descripción')
            ->extraAttributes([
                'class' =>
                    'operation-header-action--secondary',
            ])
            ->modalHeading('Editor de descripción')
            ->modalSubmitActionLabel('Guardar descripción')
            ->modalWidth('7xl')

            ->fillForm(function (): array {
                $description = $this->record->description ?? [];

                $sections = $description['sections'] ?? [];

                /*
                * Compatibilidad con el formato antiguo:
                *
                * description = [
                *     'content' => ...
                * ]
                */
                if (
                    blank($sections)
                    && filled($description['content'] ?? null)
                ) {
                    $sections = [
                        [
                            'title' => 'Descripción',
                            'content' => $description['content'],
                        ],
                    ];
                }

                /*
                * Normalizamos también las secciones antiguas
                * que todavía no tenían información de imagen.
                */
                $sections = collect($sections)
                    ->map(function (array $section): array {
                        return [
                            'title' => BriefingMarkup::toEditor(
                                $section['title'] ?? ''
                            ),

                            'content' => BriefingMarkup::toEditor(
                                $section['content'] ?? null
                            ),

                            'image_upload' =>
                                filled($section['image'] ?? null)
                                && ! Str::startsWith(
                                    strtolower((string) $section['image']),
                                    ['http://', 'https://']
                                )
                                    ? $section['image']
                                    : null,

                            'legacy_image' =>
                                filled($section['image'] ?? null)
                                && Str::startsWith(
                                    strtolower((string) $section['image']),
                                    ['http://', 'https://']
                                )
                                    ? $section['image']
                                    : null,

                            'remove_legacy_image' => false,

                            'image_position' =>
                                $section['image_position']
                                ?? 'left',

                            'image_alignment' =>
                                $section['image_alignment']
                                ?? 'left',

                            'image_width' =>
                                (string) (
                                    $section['image_width']
                                    ?? '40'
                                ),

                            'image_caption' =>
                                $section['image_caption']
                                ?? null,
                        ];
                    })
                    ->values()
                    ->all();

                return [
                    'sections' => $sections,
                ];
            })

            ->form([
                Repeater::make('sections')
                    ->label('Secciones')

                    ->schema([
                        Textarea::make('title')
                            ->label('Título')
                            ->required()
                            ->rows(2)
                            ->maxLength(1000)
                            ->helperText(
                                'Admite BBCode seguro: [b], [i], [u], [color=#ff8800], [url=...], [img]...[/img], etc.'
                            )
                            ->extraInputAttributes([
                                'data-briefing-bbcode' => '1',
                            ])
                            ->columnSpanFull(),

                        Textarea::make('content')
                            ->label('Contenido')
                            ->rows(10)
                            ->maxLength(50000)
                            ->helperText(
                                'BBCode seguro como en foro/AAR. Para imágenes remotas usa [img]https://...[/img]. No se admite HTML directo.'
                            )
                            ->extraInputAttributes([
                                'data-briefing-bbcode' => '1',
                            ])
                            ->columnSpanFull(),

                        FileUpload::make('image_upload')
                            ->label('Imagen subida')
                            ->image()
                            ->disk('public')
                            ->directory('activities/briefings')
                            ->visibility('public')
                            ->maxSize(5120)
                            ->helperText(
                                'Opcional. Sube una imagen desde tu equipo (máx. 5 MB). Las imágenes por URL se insertan dentro del BBCode con [img]...[/img].'
                            )
                            ->columnSpanFull(),

                        Hidden::make('legacy_image'),

                        Toggle::make('remove_legacy_image')
                            ->label('Quitar imagen antigua por URL')
                            ->helperText(
                                'Solo aparece en briefings antiguos que todavía guardan una URL en el campo de imagen.'
                            )
                            ->visible(
                                fn (Get $get): bool => filled(
                                    $get('legacy_image')
                                )
                            )
                            ->default(false)
                            ->columnSpanFull(),

                        Select::make('image_position')
                            ->label('Posición de la imagen')
                            ->options([
                                'left' => 'Izquierda',
                                'right' => 'Derecha',
                                'top' => 'Arriba',
                                'bottom' => 'Abajo',
                            ])
                            ->default('left')
                            ->live()
                            ->native(false),

                        Select::make('image_alignment')
                            ->label('Alineación de la imagen')
                            ->options([
                                'left' => 'Izquierda',
                                'center' => 'Centrada',
                                'right' => 'Derecha',
                            ])
                            ->default('left')
                            ->native(false)
                            ->visible(
                                fn (Get $get): bool =>
                                    in_array(
                                        $get('image_position'),
                                        ['top', 'bottom'],
                                        true
                                    )
                            ),

                        Select::make('image_width')
                            ->label('Tamaño de la imagen')
                            ->options([
                                '33' => '33%',
                                '40' => '40%',
                                '50' => '50%',
                                '66' => '66%',
                                '100' => '100%',
                            ])
                            ->default('40')
                            ->native(false),

                        TextInput::make('image_caption')
                            ->label('Pie de imagen')
                            ->placeholder('Opcional')
                            ->maxLength(255)
                            ->columnSpanFull(),
                    ])

                    ->itemLabel(
                        fn (array $state): ?string =>
                            $state['title'] ?? null
                    )

                    ->reorderableWithButtons()
                    ->collapsible()
                    ->default([])
                    ->addActionLabel('Añadir sección')
                    ->columnSpanFull(),
            ])

            ->action(function (array $data): void {
                $allowedPositions = [
                    'left',
                    'right',
                    'top',
                    'bottom',
                ];

                $allowedAlignments = [
                    'left',
                    'center',
                    'right',
                ];

                $allowedWidths = [
                    '33',
                    '40',
                    '50',
                    '66',
                    '100',
                ];

                $sections = collect(
                    $data['sections'] ?? []
                )
                    ->map(
                        function (array $section) use (
                            $allowedPositions,
                            $allowedAlignments,
                            $allowedWidths,
                        ): array {
                            $uploadedImage = BriefingMarkup::normalizeImageReference(
                                $section['image_upload'] ?? null
                            );

                            $legacyImage = BriefingMarkup::normalizeImageReference(
                                $section['legacy_image'] ?? null
                            );

                            $image = $uploadedImage;

                            if (
                                $image === null
                                && ! (bool) ($section['remove_legacy_image'] ?? false)
                            ) {
                                $image = $legacyImage;
                            }

                            $caption = trim(
                                (string) (
                                    $section['image_caption']
                                    ?? ''
                                )
                            );

                            $position =
                                $section['image_position']
                                ?? 'left';

                            $alignment =
                                $section['image_alignment']
                                ?? 'left';

                            $width = (string) (
                                $section['image_width']
                                ?? '40'
                            );

                            if (
                                ! in_array(
                                    $position,
                                    $allowedPositions,
                                    true
                                )
                            ) {
                                $position = 'left';
                            }
                            if (
                                ! in_array(
                                    $alignment,
                                    $allowedAlignments,
                                    true
                                )
                            ) {
                                $alignment = 'left';
                            }
                            if (
                                ! in_array(
                                    $width,
                                    $allowedWidths,
                                    true
                                )
                            ) {
                                $width = '40';
                            }

                            return [
                                'title' => trim(
                                    (string) (
                                        $section['title']
                                        ?? ''
                                    )
                                ),

                                'content' => trim(
                                    (string) (
                                        $section['content']
                                        ?? ''
                                    )
                                ),

                                'image' => $image,

                                'image_position' =>
                                    $position,

                                'image_alignment' =>
                                    $alignment,

                                'image_width' =>
                                    $width,

                                'image_caption' =>
                                    $caption !== ''
                                        ? $caption
                                        : null,
                            ];
                        }
                    )

                    ->filter(
                        fn (array $section): bool =>
                            $section['title'] !== ''
                            || ! empty(
                                $section['content']
                            )
                            || ! empty(
                                $section['image']
                            )
                    )

                    ->values()
                    ->all();

                $this->record->forceFill([
                    'description' => [
                        'sections' => $sections,
                    ],
                ])->save();

                $this->record->refresh();

                Notification::make()
                    ->title(
                        'Descripción actualizada'
                    )
                    ->success()
                    ->send();
            }),
            Action::make('editOrbat')
                ->label('Editar ORBAT')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--secondary',
                ])
                ->modalHeading('Editor de ORBAT')
                ->modalSubmitActionLabel('Guardar ORBAT')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => SlotQuickSelection::prepareOrbat(
                    $this->record->orbat ?? ['groups' => []]
                ))
                ->form([
                    Repeater::make('groups')
                        ->label('Grupos')
                        ->columns(3)
                        ->extraAttributes([
                            'class' => 'orbat-group-cards',
                        ])
                        ->schema([
                            Toggle::make('visible')
                                ->label('Visible')
                                ->inline(false)
                                ->live()
                                ->afterStateUpdated(function ($state, Get $get, Set $set): void {
                                    $slots = collect($get('slots') ?? [])
                                        ->map(function (array $slot) use ($state): array {
                                            $slot['visible'] = (bool) $state;

                                            return $slot;
                                        })
                                        ->values()
                                        ->all();

                                    $set('slots', $slots);
                                })
                                ->default(true),

                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),

                            /*
                            |--------------------------------------------------------------------------
                            | Mostrar / ocultar filtros
                            |--------------------------------------------------------------------------
                            |
                            | Estado únicamente visual.
                            | Nunca se guarda dentro del JSON del ORBAT.
                            |
                            */

                            Hidden::make('show_faction_filters')
                                ->default(false)
                                ->dehydrated(false),

                            /*
                            |--------------------------------------------------------------------------
                            | Facción
                            |--------------------------------------------------------------------------
                            */

                            Select::make('faction_id')
                                ->label('Facción')
                                ->options(
                                    function (Get $get): array {

                                        $countryId =
                                            $get('faction_country_filter');

                                        $armyId =
                                            $get('faction_army_filter');

                                        $selectedId =
                                            $get('faction_id');

                                        if (blank($countryId) && blank($armyId)) {
                                            return $this->orbatFactionOptions();
                                        }

                                        $query =
                                            Faction::query();

                                        /*
                                        |--------------------------------------------------------------------------
                                        | Aplicar filtros
                                        |--------------------------------------------------------------------------
                                        */

                                        if (
                                            filled($countryId)
                                            || filled($armyId)
                                        ) {
                                            $query->where(
                                                function ($query) use (
                                                    $countryId,
                                                    $armyId,
                                                    $selectedId
                                                ): void {

                                                    $query->where(
                                                        function ($query) use (
                                                            $countryId,
                                                            $armyId
                                                        ): void {

                                                            /*
                                                            * País
                                                            */

                                                            if (filled($countryId)) {
                                                                $query->whereHas(
                                                                    'army',
                                                                    fn ($armyQuery) =>
                                                                        $armyQuery->where(
                                                                            'country_id',
                                                                            $countryId
                                                                        )
                                                                );
                                                            }

                                                            /*
                                                            * Ejército
                                                            */

                                                            if (filled($armyId)) {
                                                                $query->where(
                                                                    'army_id',
                                                                    $armyId
                                                                );
                                                            }
                                                        }
                                                    );

                                                    /*
                                                    * Conservamos siempre la facción
                                                    * que ya tuviera seleccionada el grupo.
                                                    */

                                                    if (filled($selectedId)) {
                                                        $query->orWhere(
                                                            'factions.id',
                                                            $selectedId
                                                        );
                                                    }
                                                }
                                            );
                                        }

                                        return $query
                                            ->with([
                                                'side',
                                                'army.country',
                                            ])
                                            ->orderBy('name')
                                            ->get()
                                            ->mapWithKeys(
                                                fn (Faction $faction): array => [
                                                    $faction->id =>
                                                        FactionOptionLabel::make(
                                                            $faction
                                                        ),
                                                ]
                                            )
                                            ->all();
                                    }
                                )
                                ->allowHtml()
                                ->wrapOptionLabels()
                                ->extraAttributes([
                                    'class' => 'orbat-faction-field',
                                ])

                                /*
                                |--------------------------------------------------------------------------
                                | Embudo
                                |--------------------------------------------------------------------------
                                |
                                | IMPORTANTE:
                                |
                                | Ya NO tiene ->schema().
                                | Ya NO abre otro modal.
                                |
                                | Simplemente muestra / oculta los filtros
                                | dentro del modal actual del ORBAT.
                                |
                                */

                                ->suffixAction(
                                    Action::make('toggleFactionFilters')
                                        ->label('Filtrar facciones')
                                        ->icon('heroicon-o-funnel')
                                        ->iconButton()
                                        ->tooltip('Filtrar por país o ejército')

                                        /*
                                        * Naranja cuando existe algún
                                        * filtro aplicado.
                                        */

                                        ->color(
                                            fn (Get $get): string =>
                                                (
                                                    filled(
                                                        $get(
                                                            'faction_country_filter'
                                                        )
                                                    )
                                                    || filled(
                                                        $get(
                                                            'faction_army_filter'
                                                        )
                                                    )
                                                )
                                                    ? 'primary'
                                                    : 'gray'
                                        )

                                        /*
                                        * Mostrar / ocultar.
                                        */

                                        ->action(
                                            function (
                                                Get $get,
                                                Set $set
                                            ): void {

                                                $set(
                                                    'show_faction_filters',
                                                    ! (bool) $get(
                                                        'show_faction_filters'
                                                    )
                                                );
                                            }
                                        )
                                )

                                ->searchable()
                                ->preload()
                                ->required(),

                            Grid::make(1)
                            ->schema([

                                Select::make('faction_country_filter')
                                    ->label('Filtrar por país')
                                    ->options(
                                        fn (): array =>
                                            Country::query()
                                                ->orderBy('name')
                                                ->pluck('name', 'id')
                                                ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder('Todos los países')
                                    ->live()
                                    ->afterStateUpdated(
                                        function ($state, Get $get, Set $set): void {
                                            $armyId =
                                                $get('faction_army_filter');

                                            /*
                                            * Si se quita el país, el ejército puede
                                            * seguir utilizándose como filtro independiente.
                                            */
                                            if (blank($state) || blank($armyId)) {
                                                return;
                                            }

                                            /*
                                            * Si el ejército seleccionado no pertenece
                                            * al nuevo país, se limpia automáticamente.
                                            */
                                            $armyBelongsToCountry =
                                                Army::query()
                                                    ->whereKey($armyId)
                                                    ->where('country_id', $state)
                                                    ->exists();

                                            if (! $armyBelongsToCountry) {
                                                $set(
                                                    'faction_army_filter',
                                                    null
                                                );
                                            }
                                        }
                                    )
                                    ->dehydrated(false),

                                Select::make('faction_army_filter')
                                    ->label('Filtrar por ejército')
                                    ->options(
                                        function (Get $get): array {
                                            $query =
                                                Army::query()
                                                    ->orderBy('name');

                                            if (
                                                filled(
                                                    $get(
                                                        'faction_country_filter'
                                                    )
                                                )
                                            ) {
                                                $query->where(
                                                    'country_id',
                                                    $get(
                                                        'faction_country_filter'
                                                    )
                                                );
                                            }

                                            return $query
                                                ->pluck(
                                                    'name',
                                                    'id'
                                                )
                                                ->all();
                                        }
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(
                                        'Todos los ejércitos'
                                    )
                                    ->live()
                                    ->dehydrated(false),
                            ])

                            /*
                            |--------------------------------------------------------------------------
                            | Colocar debajo de "Facción"
                            |--------------------------------------------------------------------------
                            |
                            | El repeater del grupo tiene 3 columnas:
                            |
                            | 1 = Visible
                            | 2 = Nombre
                            | 3 = Facción
                            |
                            | Por eso forzamos este bloque a comenzar
                            | en la columna 3.
                            |
                            */

                            ->columnStart(3)
                            ->columnSpan(1)

                            /*
                            * Solo aparece al pulsar el embudo.
                            */

                            ->visible(
                                fn (Get $get): bool =>
                                    (bool) $get('show_faction_filters')
                            ),
                            Repeater::make('slots')
                                ->label('Slots')
                                ->columns(1)
                                ->grid(1)
                                ->extraAttributes([
                                    'class' => 'orbat-slot-cards',
                                ])
                                ->schema([
                                    Hidden::make('slot_key')
                                        ->default(fn (): string => (string) Str::ulid()),

                                    Hidden::make('slot_type_id'),

                                    Hidden::make('slot_quick_name_id'),

                                    Hidden::make('slot_choice')
                                        ->required(),

                                    Grid::make(12)
                                        ->schema([
                                            Placeholder::make('slot_choice_preview')
                                                ->label('Tipo de slot')
                                                ->content(
                                                    function (Get $get): HtmlString {
                                                        $manualName = trim(
                                                            (string) ($get('name') ?? '')
                                                        );

                                                        $choice = is_string(
                                                            $get('slot_choice')
                                                        )
                                                            ? $get('slot_choice')
                                                            : null;

                                                        $resolved = SlotQuickSelection::resolveChoice(
                                                            $choice
                                                        );

                                                        $selectedName = trim(
                                                            (string) ($resolved['name'] ?? '')
                                                        );

                                                        $slotType = SlotQuickSelection::selectedLabel(
                                                            $choice
                                                        );

                                                        $primary = $selectedName !== ''
                                                            ? $selectedName
                                                            : ($manualName !== ''
                                                                ? $manualName
                                                                : 'Sin seleccionar');

                                                        return new HtmlString(
                                                            '<div class="orbat-slot-choice-display">'
                                                            . '<strong>' . e($primary) . '</strong>'
                                                            . '<small>' . e($slotType ?? 'Escoge tipo y nombre base') . '</small>'
                                                            . '</div>'
                                                        );
                                                    }
                                                )
                                                ->extraAttributes([
                                                    'class' => 'orbat-slot-choice-field',
                                                ])
                                                ->columnSpan([
                                                    'default' => 12,
                                                    'sm' => 4,
                                                ]),

                                            TextInput::make('name')
                                                ->label('Nombre del slot')
                                                ->required()
                                                ->maxLength(255)
                                                ->live(onBlur: true)
                                                ->columnSpan([
                                                    'default' => 10,
                                                    'sm' => 7,
                                                ]),

                                            Actions::make([
                                                Action::make('chooseSlot')
                                                    ->label('')
                                                    ->icon('heroicon-o-squares-2x2')
                                                    ->tooltip('Escoger tipo de slot y nombre base')
                                                    ->color('primary')
                                                    ->iconButton()
                                                    ->extraAttributes([
                                                        'class' => 'orbat-slot-picker-icon',
                                                    ])
                                                    ->modalHeading('Escoger slot')
                                                    ->modalDescription(
                                                        'Selecciona un nombre rápido. El nombre se copiará al slot y podrás editarlo después si lo necesitas.'
                                                    )
                                                    ->modalWidth('7xl')
                                                    ->modalSubmitAction(false)
                                                    ->fillForm(
                                                        fn (mixed $schemaState): array =>
                                                            SlotQuickSelection::pickerFormData(
                                                                is_array($schemaState)
                                                                    && is_string($schemaState['slot_choice'] ?? null)
                                                                        ? $schemaState['slot_choice']
                                                                        : null
                                                            )
                                                    )
                                                    ->schema(
                                                        fn (): array => self::slotPickerSchema()
                                                    )
                                                    ->action(
                                                        function (
                                                            array $data,
                                                            Set $schemaSet
                                                        ): void {
                                                            $choice = is_string(
                                                                $data['selected_slot_choice'] ?? null
                                                            )
                                                                ? $data['selected_slot_choice']
                                                                : null;

                                                            $resolved = SlotQuickSelection::resolveChoice(
                                                                $choice
                                                            );

                                                            $schemaSet('slot_choice', $choice);
                                                            $schemaSet(
                                                                'slot_type_id',
                                                                $resolved['slot_type_id']
                                                            );
                                                            $schemaSet(
                                                                'slot_quick_name_id',
                                                                $resolved['slot_quick_name_id']
                                                            );

                                                            if (filled($resolved['name'])) {
                                                                $schemaSet(
                                                                    'name',
                                                                    $resolved['name']
                                                                );
                                                            }
                                                        }
                                                    ),
                                            ])
                                                ->label(' ')
                                                ->verticalAlignment(VerticalAlignment::End)
                                                ->alignEnd()
                                                ->extraAttributes([
                                                    'class' => 'orbat-slot-picker-actions',
                                                ])
                                                ->columnSpan([
                                                    'default' => 2,
                                                    'sm' => 1,
                                                ]),
                                        ])
                                        ->extraAttributes([
                                            'class' => 'orbat-slot-row-fields',
                                        ])
                                        ->columnSpanFull(),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->compact()
                                ->cloneable()
                                ->default([])
                                ->addActionLabel('Añadir slot')
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->reorderableWithButtons()
                        ->cloneable()
                        ->collapsible()
                        ->default([])
                        ->addActionLabel('Añadir grupo')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $usedSlotKeys = [];

                    $groups = collect($data['groups'] ?? [])
                        ->map(function (array $group) use (&$usedSlotKeys): array {
                            $slots = collect($group['slots'] ?? [])
                                ->map(function (array $slot) use (&$usedSlotKeys): array {
                                    $slotKey = $slot['slot_key'] ?? null;

                                    if (blank($slotKey) || in_array($slotKey, $usedSlotKeys, true)) {
                                        do {
                                            $slotKey = (string) Str::ulid();
                                        } while (in_array($slotKey, $usedSlotKeys, true));
                                    }

                                    $usedSlotKeys[] = $slotKey;

                                    $resolved = SlotQuickSelection::resolveChoice(
                                        isset($slot['slot_choice']) && is_string($slot['slot_choice'])
                                            ? $slot['slot_choice']
                                            : null
                                    );

                                    $slotTypeId = $resolved['slot_type_id']
                                        ?? (isset($slot['slot_type_id']) ? (int) $slot['slot_type_id'] : null);

                                    $quickNameId = $resolved['slot_quick_name_id']
                                        ?? (isset($slot['slot_quick_name_id']) ? (int) $slot['slot_quick_name_id'] : null);

                                    return [
                                        'slot_key' => $slotKey,
                                        'name' => $slot['name'] ?? ($resolved['name'] ?? ''),
                                        'slot_type_id' => $slotTypeId,
                                        'slot_quick_name_id' => $quickNameId,
                                        'visible' => (bool) ($slot['visible'] ?? true),
                                    ];
                                })
                                ->values()
                                ->all();

                            return [
                                'name' => $group['name'] ?? '',
                                'faction_id' => isset($group['faction_id']) ? (int) $group['faction_id'] : null,
                                'visible' => (bool) ($group['visible'] ?? false),
                                'slots' => $slots,
                            ];
                        })
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'orbat' => ['groups' => $groups],
                    ])->save();
                }),

            Action::make('editRadio')
                ->label('Editar radios')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--secondary',
                ])
                ->modalHeading('Editor de radios')
                ->modalSubmitActionLabel('Guardar radios')
                ->modalWidth('7xl')
                ->fillForm(function (): array {
                    $radio = $this->record->radio ?? [];

                    $networks = $radio['networks'] ?? [];

                    if (
                        blank($networks)
                        && filled($radio['content'] ?? null)
                    ) {
                        $networks = [
                            [
                                'name' => 'Radio',

                                'radio_model_id' => null,

                                'radio_model_name' => null,

                                'configuration' => [
                                    'channel' => null,
                                    'block' => null,
                                    'frequency' => null,
                                ],

                                'notes' =>
                                    $radio['content'],

                                'visible' => true,
                            ],
                        ];
                    }

                    return [
                        'networks' => $networks,
                    ];
                })
                ->form([
                    Actions::make([
                        Action::make('loadOrbatRadioNetworks')
                            ->label('Sincronizar con ORBAT')
                            ->icon('heroicon-o-arrow-path')
                            ->action(function (Get $get, Set $set): void {
                                $groupNames = collect(
                                    $this->record->orbat['groups'] ?? []
                                )
                                    ->map(
                                        fn (array $group): string =>
                                            trim((string) ($group['name'] ?? ''))
                                    )
                                    ->filter()
                                    ->unique(
                                        fn (string $name): string =>
                                            mb_strtolower($name)
                                    )
                                    ->values();

                                if ($groupNames->isEmpty()) {
                                    Notification::make()
                                        ->title('El ORBAT no tiene grupos para sincronizar.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                if ($groupNames->count() > 99) {
                                    Notification::make()
                                        ->title('No se pueden sincronizar más de 99 grupos.')
                                        ->body('El bloque de radio está limitado al rango 1–99.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $currentNetworks = collect(
                                    $get('networks') ?? []
                                )->values();

                                $currentModelIds = $currentNetworks
                                    ->pluck('radio_model_id')
                                    ->filter()
                                    ->map(fn ($id): int => (int) $id)
                                    ->unique()
                                    ->values();

                                $currentModels = RadioModel::query()
                                    ->whereIn('id', $currentModelIds)
                                    ->get()
                                    ->keyBy('id');

                                $preferredRadioModel = $currentNetworks
                                    ->map(
                                        fn (array $network) =>
                                            $currentModels->get(
                                                (int) ($network['radio_model_id'] ?? 0)
                                            )
                                    )
                                    ->first(
                                        fn (?RadioModel $model): bool =>
                                            (bool) ($model?->channel)
                                            && (bool) ($model?->block)
                                    );

                                $preferredRadioModel ??= RadioModel::query()
                                    ->where('channel', true)
                                    ->where('block', true)
                                    ->orderBy('name')
                                    ->first();

                                if (! $preferredRadioModel) {
                                    Notification::make()
                                        ->title('No hay un modelo de radio con Canal y Bloque habilitados.')
                                        ->body('Configura primero un modelo compatible para poder sincronizar el ORBAT.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $usedNetworkIndexes = [];

                                $syncedNetworks = $groupNames
                                    ->map(function (string $groupName, int $index) use (
                                        $currentNetworks,
                                        $currentModels,
                                        $preferredRadioModel,
                                        &$usedNetworkIndexes,
                                    ): array {
                                        $normalizedGroupName = mb_strtolower($groupName);

                                        $matchingIndex = $currentNetworks
                                            ->keys()
                                            ->first(function (int $networkIndex) use (
                                                $currentNetworks,
                                                $normalizedGroupName,
                                                $usedNetworkIndexes,
                                            ): bool {
                                                if (in_array($networkIndex, $usedNetworkIndexes, true)) {
                                                    return false;
                                                }

                                                return mb_strtolower(
                                                    trim((string) (
                                                        $currentNetworks[$networkIndex]['name'] ?? ''
                                                    ))
                                                ) === $normalizedGroupName;
                                            });

                                        $network = $matchingIndex !== null
                                            ? $currentNetworks[$matchingIndex]
                                            : static::blankRadioNetwork($groupName);

                                        if ($matchingIndex !== null) {
                                            $usedNetworkIndexes[] = $matchingIndex;
                                        }

                                        $currentModel = $currentModels->get(
                                            (int) ($network['radio_model_id'] ?? 0)
                                        );

                                        if (
                                            ! $currentModel
                                            || ! $currentModel->channel
                                            || ! $currentModel->block
                                        ) {
                                            $network['radio_model_id'] = $preferredRadioModel->id;
                                            $network['radio_model_name'] = $preferredRadioModel->name;
                                        }

                                        $network['name'] = $groupName;
                                        $network['configuration'] = array_merge(
                                            $network['configuration'] ?? [],
                                            [
                                                'channel' => 1,
                                                'block' => $index + 1,
                                            ],
                                        );
                                        $network['visible'] = (bool) ($network['visible'] ?? true);

                                        return $network;
                                    });

                                $customNetworks = $currentNetworks
                                    ->reject(
                                        fn (array $network, int $networkIndex): bool =>
                                            in_array($networkIndex, $usedNetworkIndexes, true)
                                    );

                                $set(
                                    'networks',
                                    $syncedNetworks
                                        ->concat($customNetworks)
                                        ->values()
                                        ->all()
                                );

                                Notification::make()
                                    ->title($groupNames->count() . ' radios sincronizadas con el ORBAT.')
                                    ->success()
                                    ->send();
                            }),

                        
                        Action::make('addVehiclesRadioNetwork')
                            ->label('Vehículos')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Vehículos'))
                                    ->values()
                                    ->all()
                            )),

                        Action::make('addGlobalRadioNetwork')
                            ->label('Global')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Global'))
                                    ->values()
                                    ->all()
                            )),
                        Action::make('addAirRadioNetwork')
                            ->label('Aire')
                            ->action(fn (Get $get, Set $set) => $set(
                                'networks',
                                collect($get('networks') ?? [])
                                    ->push(static::blankRadioNetwork('Aire'))
                                    ->values()
                                    ->all()
                            )),    
                    ]),

                    Repeater::make('networks')
                        ->label('Redes de radio')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255)
                                ->columnSpan([
                                    'default' => 12,
                                    'md' => 4,
                                ]),

                            Select::make('radio_model_id')
                                ->label('Modelo de radio')
                                ->options(fn (): array => RadioModel::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->preload()
                                ->live()
                                ->afterStateUpdated(function ($state, Set $set): void {
                                    $radioModel = RadioModel::query()->find($state);

                                    $set('radio_model_name', $radioModel?->name);
                                    $set('configuration.channel', null);
                                    $set('configuration.block', null);
                                    $set('configuration.frequency', null);
                                })
                                ->required()
                                ->columnSpan([
                                    'default' => 12,
                                    'md' => 4,
                                ]),

                            Hidden::make('radio_model_name'),

                            TextInput::make('configuration.channel')
                                ->label('Canal')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(99)
                                ->extraAttributes([
                                    'class' => 'radio-number-field',
                                ])
                                ->columnSpan([
                                    'default' => 6,
                                    'md' => 1,
                                ])
                                ->visible(
                                    fn (Get $get): bool =>
                                        static::radioModelSupports(
                                            $get('radio_model_id'),
                                            'channel'
                                        )
                                ),

                            TextInput::make('configuration.block')
                                ->label('Bloque')
                                ->numeric()
                                ->integer()
                                ->minValue(1)
                                ->maxValue(99)
                                ->extraAttributes([
                                    'class' => 'radio-number-field',
                                ])
                                ->columnSpan([
                                    'default' => 6,
                                    'md' => 1,
                                ])
                                ->visible(
                                    fn (Get $get): bool =>
                                        static::radioModelSupports(
                                            $get('radio_model_id'),
                                            'block'
                                        )
                                ),

                            TextInput::make('configuration.frequency')
                                ->label('Frecuencia')
                                ->numeric()
                                ->step('0.001')
                                ->suffix('MHz')
                                ->columnSpan([
                                    'default' => 12,
                                    'md' => 2,
                                ])
                                ->visible(
                                    fn (Get $get): bool =>
                                        static::radioModelSupports(
                                            $get('radio_model_id'),
                                            'frequency'
                                        )
                                ),

                            Textarea::make('notes')
                                ->label('Notas')
                                ->rows(1)
                                ->columnSpan([
                                    'default' => 12,
                                    'md' => 10,
                                ]),

                            Toggle::make('visible')
                                ->label('Visible')
                                ->inline(false)
                                ->default(true)
                                ->columnSpan([
                                    'default' => 12,
                                    'md' => 2,
                                ]),
                        ])
                        ->columns(12)
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->cloneable()
                        ->default([])
                        ->addActionLabel('Añadir red')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $submittedNetworks = collect(
                        $data['networks'] ?? []
                    );

                    $radioModels = RadioModel::query()
                        ->whereIn(
                            'id',
                            $submittedNetworks
                                ->pluck('radio_model_id')
                                ->filter()
                                ->map(fn ($id): int => (int) $id)
                                ->unique()
                                ->values()
                        )
                        ->get()
                        ->keyBy('id');

                    $networks = $submittedNetworks
                        ->map(function (
                            array $network
                        ) use ($radioModels): array {
                            $radioModel = isset($network['radio_model_id'])
                                ? $radioModels->get((int) $network['radio_model_id'])
                                : null;

                            return [
                                'name' =>
                                    $network['name']
                                    ?? '',

                                'radio_model_id' =>
                                    isset(
                                        $network[
                                            'radio_model_id'
                                        ]
                                    )
                                        ? (int) $network[
                                            'radio_model_id'
                                        ]
                                        : null,

                                'radio_model_name' =>
                                    $radioModel?->name
                                    ?? $network[
                                        'radio_model_name'
                                    ]
                                    ?? null,

                                'configuration' => [
                                    'channel' =>
                                        filled(
                                            $network[
                                                'configuration'
                                            ]['channel']
                                            ?? null
                                        )
                                            ? (int) $network[
                                                'configuration'
                                            ]['channel']
                                            : null,

                                    'block' =>
                                        filled(
                                            $network[
                                                'configuration'
                                            ]['block']
                                            ?? null
                                        )
                                            ? (int) $network[
                                                'configuration'
                                            ]['block']
                                            : null,

                                    'frequency' =>
                                        filled(
                                            $network[
                                                'configuration'
                                            ]['frequency']
                                            ?? null
                                        )
                                            ? (float) $network[
                                                'configuration'
                                            ]['frequency']
                                            : null,
                                ],

                                'notes' =>
                                    $network['notes']
                                    ?? null,

                                'visible' =>
                                    (bool) (
                                        $network['visible']
                                        ?? true
                                    ),
                            ];
                        })

                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'radio' => [
                            'networks' => $networks,
                        ],
                    ])->save();

                    $this->record->refresh();

                    Notification::make()
                        ->title('Radios actualizadas')
                        ->success()
                        ->send();
                }),  

            Action::make('editAddons')
                ->label('Editar addons')
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--secondary',
                ])
                ->modalHeading('Editor de addons')
                ->modalSubmitActionLabel('Guardar addons')
                ->modalWidth('3xl')
                ->fillForm(fn (): array => [
                    'addon_ids' => $this->record->addons['addon_ids'] ?? [],
                    'addon_preset_id' => null,
                    'addons_text' => '',
                ])
                ->form([
                    Select::make('addon_preset_id')
                        ->label('Preset de addons')
                        ->options(fn (): array => AddonPreset::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->searchable()
                        ->preload()
                        ->dehydrated(false),

                    Textarea::make('addons_text')
                        ->label('Listado de addons')
                        ->helperText('Pega aquí el listado en texto plano, un addon por línea.')
                        ->rows(3)
                        ->dehydrated(false)
                        ->columnSpanFull(),

                    Actions::make([
                        Action::make('applyAddonPreset')
                            ->label('Aplicar preset')
                            ->action(function (Get $get, Set $set): void {
                                $presetId = $get('addon_preset_id');

                                if (blank($presetId)) {
                                    return;
                                }

                                $preset = AddonPreset::query()->find($presetId);

                                $presetAddonIds = $preset
                                    ? $preset->addons()
                                        ->where('active', true)
                                        ->pluck('addons.id')
                                        ->map(fn (int $id): string => (string) $id)
                                        ->all()
                                    : [];

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($presetAddonIds)
                                    ->unique()
                                    ->values()
                                    ->all());
                            }),

                        Action::make('selectMandatoryAddons')
                            ->label('Seleccionar obligatorios')
                            ->action(function (Get $get, Set $set): void {
                                $mandatoryAddonIds = Addon::query()
                                    ->where('active', true)
                                    ->where('mandatory', true)
                                    ->pluck('id')
                                    ->map(fn (int $id): string => (string) $id)
                                    ->all();

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($mandatoryAddonIds)
                                    ->unique()
                                    ->values()
                                    ->all());
                            }),

                        Action::make('importAddonsHtml')
                            ->label('Importar listado')
                            ->action(function (Get $get, Set $set): void {
                                $addonNames = static::extractAddonNamesFromText($get('addons_text'));

                                if (blank($addonNames)) {
                                    Notification::make()
                                        ->title('No se han encontrado addons en el listado.')
                                        ->warning()
                                        ->send();

                                    return;
                                }

                                $addonIds = Addon::query()
                                    ->where('active', true)
                                    ->whereIn('name', $addonNames)
                                    ->pluck('id')
                                    ->all();

                                // De momento no creamos automáticamente addons que no existan.
                                // Si más adelante queremos recuperarlo, esta era la lógica:
                                // $addonIds = collect($addonNames)
                                //     ->map(fn (string $name): int => Addon::query()
                                //         ->firstOrCreate(['name' => $name], [
                                //             'description' => null,
                                //             'mandatory' => false,
                                //         ])
                                //         ->id)
                                //     ->all();

                                $set('addon_ids', collect($get('addon_ids') ?? [])
                                    ->merge($addonIds)
                                    ->map(fn ($addonId): string => (string) $addonId)
                                    ->unique()
                                    ->values()
                                    ->all());

                                Notification::make()
                                    ->title(count($addonIds) . ' addons importados.')
                                    ->success()
                                    ->send();
                            }),

                        Action::make('downloadAddonsHtml')
                            ->label('Descargar HTML')
                            ->action(function (Get $get) {
                                $addonIds = collect($get('addon_ids') ?? [])
                                    ->map(fn ($addonId): int => (int) $addonId)
                                    ->filter()
                                    ->values()
                                    ->all();

                                $filename = Str::slug($this->record->name ?: 'operacion') . '-addons.html';

                                return response()->streamDownload(
                                    fn () => print static::buildAddonsHtml($addonIds),
                                    $filename,
                                    ['Content-Type' => 'text/html; charset=UTF-8']
                                );
                            }),

                        
                    ]),

                    CheckboxList::make('addon_ids')
                        ->label('Addons')
                        ->options(fn (): array => Addon::query()
                            ->where('active', true)
                            ->orderBy('mandatory', 'desc')
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all())
                        ->descriptions(fn (): array => Addon::query()
                            ->where('active', true)
                            ->orderBy('mandatory', 'desc')
                            ->orderBy('name')
                            ->get()
                            ->mapWithKeys(fn (Addon $addon): array => [
                                $addon->id => trim(($addon->mandatory ? 'Obligatorio. ' : 'Opcional. ') ),
                            ])
                            ->all())
                        ->bulkToggleable()
                        ->searchable()
                        ->columns(2)
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $addonIds = collect($data['addon_ids'] ?? [])
                        ->map(fn ($addonId): int => (int) $addonId)
                        ->filter()
                        ->values()
                        ->all();

                    $this->record->forceFill([
                        'addons' => ['addon_ids' => $addonIds],
                    ])->save();
                }),



            Action::make('duplicateOperation')
                ->label('Duplicar')
                ->visible(
                    fn (): bool => ActivityTypeAccess::can(
                        auth()->user(),
                        'activities',
                        'create',
                        $this->record->activity_type_id,
                    )
                )
                ->extraAttributes([
                    'class' =>
                        'operation-header-action--primary',
                ])
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Duplicar actividad')
                ->modalDescription('Se creará una copia de la actividad actual sin duplicar sus eventos.')
                ->action(function (): void {
                    abort_unless(
                        ActivityTypeAccess::can(
                            auth()->user(),
                            'activities',
                            'create',
                            $this->record->activity_type_id,
                        ),
                        403
                    );

                    $duplicate = $this->record->replicate();

                    $duplicate->name = trim($this->record->name . ' (copia)');
                    $duplicate->orbat = static::regenerateOrbatSlotKeys($this->record->orbat ?? []);
                    $duplicate->save();

                    $enemyFactions = $this->record
                        ->enemyFactions()
                        ->orderBy('name')
                        ->get([
                            'factions.id',
                            'factions.name',
                        ]);

                    $enemyFactionIds = $enemyFactions
                        ->pluck('id')
                        ->all();

                    $duplicate
                        ->enemyFactions()
                        ->sync($enemyFactionIds);


                    /*
                    |--------------------------------------------------------------------------
                    | Auditoría de la duplicación
                    |--------------------------------------------------------------------------
                    |
                    | El modelo Activity ya registra su creación mediante Auditable.
                    | Aquí registramos específicamente:
                    |
                    | - de qué actividad procede;
                    | - las facciones enemigas copiadas;
                    | - la modificación de activity_enemy_faction.
                    |
                    */

                    app(AuditLogger::class)
                        ->change(
                            subject: $duplicate,

                            event: 'activity_duplicated',

                            old: [],

                            new: [
                                'source_activity_id' =>
                                    $this->record->getKey(),

                                'source_activity_name' =>
                                    $this->record->name,

                                'enemy_factions' =>
                                    $enemyFactions
                                        ->map(
                                            fn (Faction $faction): array => [
                                                'id' => $faction->id,
                                                'name' => $faction->name,
                                            ]
                                        )
                                        ->values()
                                        ->all(),
                            ],

                            properties: [
                                'action' => 'duplicate',

                                'source_activity_id' =>
                                    $this->record->getKey(),

                                'relation' =>
                                    'enemyFactions',

                                'table' =>
                                    'activity_enemy_faction',
                            ],
                        );

                    Notification::make()
                        ->title('Actividad duplicada.')
                        ->success()
                        ->send();

                    $this->redirect(ActivityResource::getUrl('edit', ['record' => $duplicate]));
                }),
        ];
    }

    protected static function regenerateOrbatSlotKeys(array $orbat): array
    {
        $orbat['groups'] = collect($orbat['groups'] ?? [])
            ->map(function (array $group): array {
                $group['slots'] = collect($group['slots'] ?? [])
                    ->map(function (array $slot): array {
                        $slot['slot_key'] = (string) Str::ulid();

                        return $slot;
                    })
                    ->values()
                    ->all();

                return $group;
            })
            ->values()
            ->all();

        return $orbat;
    }

    protected static function buildAddonsHtml(array $addonIds): string
    {
        $addons = Addon::query()
            ->whereIn('id', $addonIds)
            ->orderBy('mandatory', 'desc')
            ->orderBy('name')
            ->get();

        $rows = $addons
            ->map(function (Addon $addon): string {
                $name = htmlspecialchars($addon->name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

                return <<<HTML
  <tr data-type="ModContainer">
    <td data-type="DisplayName">{$name}</td>
  </tr>
HTML;
            })
            ->implode("\n");

        return <<<HTML
<html><head></head><body><table>
  <tbody>{$rows}
</tbody></table></body></html>
HTML;
    }

    protected static function extractAddonNamesFromText(?string $text): array
    {
        if (blank($text)) {
            return [];
        }

        return collect(preg_split('/\R/', $text))
            ->map(fn (string $name): string => trim($name))
            ->filter()
            ->unique(fn (string $name): string => mb_strtolower($name))
            ->values()
            ->all();
    }

    protected static function radioModelSupports(
        mixed $radioModelId,
        string $capability
    ): bool {
        if (
            blank($radioModelId)
            || ! in_array($capability, ['channel', 'block', 'frequency'], true)
        ) {
            return false;
        }

        $radioModelId = (int) $radioModelId;

        if (! array_key_exists($radioModelId, static::$radioModelCapabilitiesCache)) {
            $radioModel = RadioModel::query()
                ->select(['id', 'channel', 'block', 'frequency'])
                ->find($radioModelId);

            static::$radioModelCapabilitiesCache[$radioModelId] = [
                'channel' => (bool) ($radioModel?->channel),
                'block' => (bool) ($radioModel?->block),
                'frequency' => (bool) ($radioModel?->frequency),
            ];
        }

        return static::$radioModelCapabilitiesCache[$radioModelId][$capability];
    }

    protected static function blankRadioNetwork(string $name): array
    {
        return [
            'name' => $name,
            'radio_model_id' => null,
            'radio_model_name' => null,
            'configuration' => [
                'channel' => null,
                'block' => null,
                'frequency' => null,
            ],
            'notes' => null,
            'visible' => true,
        ];
    }
    private static function slotTypeOptionLabel(
        SlotType $slotType
    ): string {

        $name =
            e($slotType->name);

        $description =
            trim(
                strip_tags(
                    (string) $slotType->description
                )
            );

        $description =
            filled($description)
                ? e(
                    \Illuminate\Support\Str::limit(
                        $description,
                        105
                    )
                )
                : 'Sin descripción.';

        return <<<HTML
            <div class="slot-type-select-option">
                <div class="slot-type-select-option__name">
                    {$name}
                </div>

                <div class="slot-type-select-option__description">
                    {$description}
                </div>
            </div>
        HTML;
    }
}
