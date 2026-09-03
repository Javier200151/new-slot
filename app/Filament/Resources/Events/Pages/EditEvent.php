<?php

namespace App\Filament\Resources\Events\Pages;

use Illuminate\Validation\ValidationException;
use App\Filament\Resources\Events\EventResource;
use App\Models\Faction;
use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Services\CommunityNotificationService;
use App\Models\EventStatus;
use App\Models\Activity;
use App\Models\User;
use App\Support\FactionOptionLabel;
use App\Support\ActivityTypeConfiguration;
use App\Services\CourseMetopaAwardService;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    public function mount(int | string $record): void
    {
        parent::mount($record);

        if (request()->boolean('awardCourseMetopa')) {
            $service = app(CourseMetopaAwardService::class);

            if (
                $service->canAwardForUser(
                    $this->record,
                    auth()->user(),
                )
            ) {
                $this->mountAction('awardCourseMetopa');
            }
        }
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
                        'event-header-action--primary',
                ]),

            $this->getCancelFormAction()
                ->label('Cancelar')
                ->extraAttributes([
                    'class' =>
                        'event-header-action--primary',
                ]),

            DeleteAction::make()
                ->extraAttributes([
                    'class' =>
                        'event-header-action--primary',
                ]),

            ForceDeleteAction::make()
                ->extraAttributes([
                    'class' =>
                        'event-header-action--primary',
                ]),

            RestoreAction::make()
                ->extraAttributes([
                    'class' =>
                        'event-header-action--primary',
                ]),

            Action::make('awardCourseMetopa')
                ->label('Entregar metopa del curso')
                ->icon('heroicon-o-trophy')
                ->color('success')
                ->visible(
                    fn (): bool =>
                        app(CourseMetopaAwardService::class)
                            ->canAwardForUser(
                                $this->record,
                                auth()->user(),
                            )
                )
                ->fillForm(
                    function (): array {
                        $studentIds = app(CourseMetopaAwardService::class)
                            ->students($this->record)
                            ->pluck('id')
                            ->map(fn ($id): int => (int) $id)
                            ->all();

                        return [
                            'user_ids' => $studentIds,
                        ];
                    }
                )
                ->form([
                    Select::make('user_ids')
                        ->label('Alumnos / destinatarios')
                        ->multiple()
                        ->options(
                            User::query()
                                ->orderBy('nick')
                                ->pluck('nick', 'id')
                        )
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText(
                            'Se precargan los alumnos detectados en el ORBAT. '
                            .'Puedes quitar usuarios o añadir otros antes de entregar.'
                        ),
                ])
                ->modalHeading('Entregar metopa del curso')
                ->modalDescription(
                    function (): string {
                        $service = app(CourseMetopaAwardService::class);
                        $students = $service->students($this->record);
                        $metopa = $this->record->activity?->metopa;

                        return 'Metopa: "'
                            . ($metopa?->name ?? 'Sin metopa')
                            . '". Se han precargado '
                            . $students->count()
                            . ' alumno(s) desde el ORBAT. '
                            . 'Las asignaciones que ya existan conservarán su fecha.';
                    }
                )
                ->modalSubmitActionLabel('Aceptar y entregar')
                ->requiresConfirmation()
                ->action(
                    function (array $data): void {
                        $result = app(CourseMetopaAwardService::class)
                            ->award(
                                $this->record,
                                $data['user_ids'] ?? [],
                            );

                        $counts = $result['results'];
                        $newAwards = $counts['created'] + $counts['restored'];

                        Notification::make()
                            ->title('Metopa del curso procesada')
                            ->body(
                                $newAwards
                                . ' nueva(s) asignación(es); '
                                . $counts['already_exists']
                                . ' ya existían y conservaron su fecha.'
                            )
                            ->success()
                            ->send();
                    }
                ),

            Action::make('editOrbatVisibility')
                ->label('Editar ORBAT')
                ->extraAttributes([
                    'class' =>
                        'event-header-action--secondary',
                ])
                ->modalHeading('Editor de ORBAT del evento')
                ->modalSubmitActionLabel('Guardar visibilidad')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => static::prepareOrbatVisibilityForm($this->record->orbat ?? []))
                ->form([
                    Repeater::make('groups')
                        ->label('Grupos')
                        ->addable(false)
                        ->deletable(false)
                        ->reorderable(false)
                        ->columns(3)
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
                                ->disabled()
                                ->dehydrated(),

                            Select::make('faction_id')
                                ->label('Facción')
                                ->options(
                                    function (Get $get): array {
                                        $factionId =
                                            $get('faction_id');

                                        if (blank($factionId)) {
                                            return [];
                                        }

                                        $faction =
                                            Faction::query()
                                                ->with([
                                                    'side',
                                                    'army.country',
                                                ])
                                                ->find($factionId);

                                        if (! $faction) {
                                            return [];
                                        }

                                        return [
                                            $faction->id =>
                                                FactionOptionLabel::make(
                                                    $faction
                                                ),
                                        ];
                                    }
                                )
                                ->allowHtml()
                                ->placeholder('Sin facción')
                                ->disabled()
                                ->dehydrated(false),

                            Repeater::make('slots')
                                ->label('Slots')
                                ->addable(false)
                                ->deletable(false)
                                ->reorderable(false)
                                ->columns(3)
                                ->schema([
                                    TextInput::make('name')
                                        ->label('Nombre')
                                        ->disabled()
                                        ->dehydrated(),

                                    TextInput::make('slot_type_name')
                                        ->label('Tipo de slot')
                                        ->disabled()
                                        ->dehydrated(false),

                                    Toggle::make('visible')
                                        ->label('Visible')
                                        ->inline(false)
                                        ->default(true),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->collapsible()
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->collapsible()
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $orbat = $this->record->orbat ?? ['groups' => []];

                    foreach ($orbat['groups'] ?? [] as $groupIndex => $group) {
                        $orbat['groups'][$groupIndex]['visible'] = (bool) ($data['groups'][$groupIndex]['visible'] ?? false);

                        foreach ($group['slots'] ?? [] as $slotIndex => $slot) {
                            $orbat['groups'][$groupIndex]['slots'][$slotIndex]['visible'] = (bool) ($data['groups'][$groupIndex]['slots'][$slotIndex]['visible'] ?? false);
                        }
                    }

                    $this->record->forceFill([
                        'orbat' => $orbat,
                    ])->save();

                    $conflicts =
                        $this
                            ->findAssignedSlotsUnavailableInOrbat(
                                $orbat
                            );

                    if ($conflicts !== []) {
                        $this
                            ->notifyOrbatAssignmentConflicts(
                                $conflicts,
                                'No se puede modificar el ORBAT'
                            );

                        return;
}

                    Notification::make()
                        ->title('Visibilidad del ORBAT actualizada.')
                        ->success()
                        ->send();
                }),

            Action::make('restoreOperationOrbat')
                ->label('Recuperar ORBAT original')
                ->extraAttributes([
                    'class' =>
                        'event-header-action--secondary',
                ])
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recuperar ORBAT original')
                ->modalDescription(
                    'Se reemplazará el ORBAT del evento por el ORBAT '
                    . 'actual del actividad asignado. Si existen usuarios '
                    . 'o aliados ocupando slots que ya no existen o están '
                    . 'ocultos en el nuevo ORBAT, la operación será bloqueada.'
                )
                ->action(function (): void {
                    $this->record->load(
                        'activity'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | ORBAT que queremos recuperar
                    |--------------------------------------------------------------------------
                    */

                    $newOrbat =
                        $this->record
                            ->activity
                            ?->orbat
                        ?? [
                            'groups' => [],
                        ];


                    /*
                    |--------------------------------------------------------------------------
                    | Comprobar asignaciones
                    |--------------------------------------------------------------------------
                    |
                    | Si el ORBAT actual del actividad ya no contiene alguno
                    | de los slots ocupados del evento, no permitimos reemplazarlo.
                    |
                    */

                    $conflicts =
                        $this
                            ->findAssignedSlotsUnavailableInOrbat(
                                $newOrbat
                            );

                    if ($conflicts !== []) {
                        $this
                            ->notifyOrbatAssignmentConflicts(
                                $conflicts,
                                'No se puede recuperar el ORBAT'
                            );

                        return;
                    }


                    /*
                    |--------------------------------------------------------------------------
                    | Recuperar
                    |--------------------------------------------------------------------------
                    */

                    $this->record->forceFill([
                        'orbat' => $newOrbat,
                    ])->save();

                    Notification::make()
                        ->title(
                            'ORBAT original recuperado.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        EventResource::getUrl(
                            'edit',
                            [
                                'record' =>
                                    $this->record,
                            ]
                        )
                    );
                }),
        ];
    }

    protected function getFormActions(): array
    {
        return [
            $this->getSaveFormAction()
                ->label('Guardar cambios'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function mutateFormDataBeforeSave(
        array $data
    ): array {
        /*
        |--------------------------------------------------------------------------
        | El actividad de un evento es inmutable
        |--------------------------------------------------------------------------
        |
        | El actividad se selecciona únicamente al crear el evento.
        | Una vez creado, no puede cambiarse ni siquiera manipulando
        | manualmente la petición de Livewire.
        |
        */

        $originalActivityId =
            (int) $this->record->activity_id;

        if (
            array_key_exists('activity_id', $data)
            && (int) $data['activity_id'] !== $originalActivityId
        ) {
            throw ValidationException::withMessages([
                'data.activity_id' =>
                    'El actividad de un evento no puede modificarse '
                    . 'una vez creado. Si necesitas otro actividad, '
                    . 'elimina este evento y crea uno nuevo.',
            ]);
        }

        /*
        * Nos aseguramos además de trabajar siempre
        * con el actividad original del evento.
        */
        $data['activity_id'] =
            $originalActivityId;
            
        /*
        |--------------------------------------------------------------------------
        | Recuperar actividad y estado del evento
        |--------------------------------------------------------------------------
        */

        $activity =
            Activity::query()
                ->with('activityStatus')
                ->find($data['activity_id']);

        $eventStatus =
            EventStatus::query()
                ->find($data['event_status_id']);


        /*
        |--------------------------------------------------------------------------
        | Protección de publicación
        |--------------------------------------------------------------------------
        |
        | Un actividad BORRADOR puede verse públicamente y puede tener
        | un evento BORRADOR preparado.
        |
        | Lo que no permitimos es que dicho evento pase a ACTIVO o
        | FINALIZADO mientras el actividad continúe en BORRADOR.
        |
        */

        if (
            $activity?->activityStatus?->name === 'BORRADOR'
            && $eventStatus?->name !== 'BORRADOR'
        ) {
            throw ValidationException::withMessages([
                'data.event_status_id' =>
                    'No puedes publicar este evento porque '
                    . 'el actividad seleccionado todavía está '
                    . 'en BORRADOR.',
            ]);
        }

        if (
            $activity?->editor_ally_id
            || $this->record->slots()->whereNotNull('ally_id')->exists()
        ) {
            $data['multiclans'] = true;
        }

        return ActivityTypeConfiguration::normalizeEventData(
            $data,
            $originalActivityId,
        );
    }

    protected function findAssignedSlotsUnavailableInOrbat(
        array $orbat
    ): array {
        /*
        |--------------------------------------------------------------------------
        | Slots realmente visibles en el nuevo ORBAT
        |--------------------------------------------------------------------------
        |
        | Para que un slot esté disponible:
        |
        | - el grupo debe estar visible;
        | - el slot debe estar visible;
        | - debe tener slot_key.
        |
        */

        $visibleSlotKeys =
            collect($orbat['groups'] ?? [])
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
        | Asignaciones actuales
        |--------------------------------------------------------------------------
        */

        return $this->record
            ->slots()
            ->with([
                'user',
                'ally',
            ])
            ->where(
                function ($query): void {
                    $query
                        ->whereNotNull('user_id')
                        ->orWhereNotNull(
                            'ally_id'
                        );
                }
            )
            ->get()

            /*
            * Nos quedamos únicamente con
            * asignaciones cuyo slot dejaría
            * de estar disponible.
            */
            ->reject(
                fn ($assignment): bool =>
                    $visibleSlotKeys->contains(
                        (string)
                        $assignment->slot_key
                    )
            )
            ->map(
                function ($assignment): array {
                    return [
                        'slot_key' =>
                            (string)
                            $assignment->slot_key,

                        'slot_group' =>
                            $assignment->slot_group
                            ?: 'Grupo sin nombre',

                        'slot_name' =>
                            $assignment->name
                            ?: 'Slot sin nombre',

                        'assignee' =>
                            $assignment->user?->nick
                            ?? $assignment->ally?->name
                            ?? 'Asignación desconocida',
                    ];
                }
            )
            ->values()
            ->all();
    }

    protected function notifyOrbatAssignmentConflicts(
        array $conflicts,
        string $title
        ): void {
            $examples =
                collect($conflicts)
                    ->take(5)
                    ->map(
                        fn (array $conflict): string =>
                            $conflict['assignee']
                            . ' — '
                            . $conflict['slot_group']
                            . ' · '
                            . $conflict['slot_name']
                    )
                    ->implode('; ');

            $remaining =
                max(
                    0,
                    count($conflicts) - 5
                );

            $body =
                'Esta modificación dejaría '
                . 'usuarios o aliados asignados '
                . 'a slots ocultos o inexistentes. '
                . 'Mueve o desapunta primero '
                . 'a esas personas.';

            if ($examples !== '') {
                $body .= ' Asignaciones afectadas: '
                    . $examples;
            }

            if ($remaining > 0) {
                $body .= ' y '
                    . $remaining
                    . ' más.';
            }

            Notification::make()
                ->title($title)
                ->body($body)
                ->danger()
                ->persistent()
                ->send();
    }

    protected static function prepareOrbatVisibilityForm(array $orbat): array
    {
        $groups = $orbat['groups'] ?? [];

        $allSlots = collect($groups)
            ->flatMap(fn (array $group): array => $group['slots'] ?? []);

        $slotTypeNames = SlotType::query()
            ->whereIn(
                'id',
                $allSlots
                    ->pluck('slot_type_id')
                    ->filter()
                    ->unique()
            )
            ->pluck('name', 'id');

        $quickCategories = SlotTypeQuickName::query()
            ->whereIn(
                'id',
                $allSlots
                    ->pluck('slot_quick_name_id')
                    ->filter()
                    ->unique()
            )
            ->pluck('category', 'id');

        return [
            'groups' => collect($groups)
                ->map(fn (array $group): array => [
                    'visible' => (bool) ($group['visible'] ?? true),
                    'name' => $group['name'] ?? '',
                    'faction_id' => isset($group['faction_id'])
                        ? (int) $group['faction_id']
                        : null,
                    'slots' => collect($group['slots'] ?? [])
                        ->map(fn (array $slot): array => [
                            'visible' => (bool) ($slot['visible'] ?? true),
                            'name' => $slot['name'] ?? '',
                            'slot_type_name' =>
                                $quickCategories[(int) ($slot['slot_quick_name_id'] ?? 0)]
                                ?? $slotTypeNames[(int) ($slot['slot_type_id'] ?? 0)]
                                ?? 'Sin tipo',
                        ])
                        ->values()
                        ->all(),
                ])
                ->values()
                ->all(),
        ];
    }
    protected function afterSave(): void
    {
        $this->record->loadMissing(
            'eventStatus'
        );

        if (
            $this->record->eventStatus?->name
            !== 'ACTIVO'
        ) {
            return;
        }

        app(
            CommunityNotificationService::class
        )->eventPublished(
            $this->record
        );
    }
}
