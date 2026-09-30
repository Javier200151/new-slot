<?php

namespace App\Filament\Resources\Events\Pages;

use Illuminate\Validation\ValidationException;
use App\Filament\Resources\Events\EventResource;
use App\Models\SlotType;
use App\Models\SlotTypeQuickName;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Flex;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Support\Enums\VerticalAlignment;
use App\Services\CommunityNotificationService;
use App\Models\EventStatus;
use App\Models\Activity;
use App\Models\User;
use App\Support\ActivityTypeConfiguration;
use App\Services\CourseMetopaAwardService;
use App\Services\EventOrbatSyncService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\HtmlString;

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
                ->modalWidth('3xl')
                ->fillForm(fn (): array => static::prepareOrbatVisibilityForm($this->record->orbat ?? []))
                ->form(fn (): array => $this->orbatVisibilitySchema())
                ->action(function (array $data): void {
                    $orbat = $this->record->orbat ?? ['groups' => []];

                    foreach ($orbat['groups'] ?? [] as $groupIndex => $group) {
                        $orbat['groups'][$groupIndex]['visible'] = (bool) (
                            $data[static::orbatGroupVisibilityField($groupIndex)]
                            ?? ($group['visible'] ?? true)
                        );

                        foreach ($group['slots'] ?? [] as $slotIndex => $slot) {
                            $orbat['groups'][$groupIndex]['slots'][$slotIndex]['visible'] = (bool) (
                                $data[static::orbatSlotVisibilityField($groupIndex, $slotIndex)]
                                ?? ($slot['visible'] ?? true)
                            );
                        }
                    }

                    /*
                    |--------------------------------------------------------------------------
                    | Validar ANTES de guardar
                    |--------------------------------------------------------------------------
                    |
                    | El ORBAT del evento puede tener usuarios/aliados asignados en
                    | event_slots. Si ocultamos un grupo o slot ocupado, la asignación
                    | quedaría huérfana desde el punto de vista del ORBAT público.
                    |
                    | Es importante comprobar los conflictos antes de persistir el JSON.
                    | Antes se guardaba primero y se validaba después: Filament mostraba
                    | el error, pero el slot ya quedaba oculto y el ocupante continuaba
                    | en event_slots.
                    |
                    */

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

                    $this->record->forceFill([
                        'orbat' => $orbat,
                    ])->save();

                    Notification::make()
                        ->title('Visibilidad del ORBAT actualizada.')
                        ->success()
                        ->send();
                }),

            Action::make('syncOperationOrbat')
                ->label('Sincronizar ORBAT')
                ->icon('heroicon-o-arrow-path')
                ->extraAttributes([
                    'class' =>
                        'event-header-action--secondary',
                ])
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Sincronizar ORBAT del evento')
                ->modalDescription(
                    fn (): string => app(EventOrbatSyncService::class)
                        ->confirmationText($this->record)
                )
                ->modalSubmitActionLabel('Sí, sincronizar')
                ->action(function (): void {
                    $result = app(EventOrbatSyncService::class)
                        ->sync(
                            $this->record,
                            auth()->user(),
                        );

                    Notification::make()
                        ->title('ORBAT sincronizado')
                        ->body(
                            $result['slots'].' slots sincronizados · '
                            .$result['updated_assignments'].' asignaciones actualizadas · '
                            .$result['removed_slots'].' registros eliminados porque sus slots ya no existen.'
                        )
                        ->success()
                        ->send();

                    $this->redirect(
                        EventResource::getUrl(
                            'edit',
                            ['record' => $this->record],
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

    protected function orbatVisibilitySchema(): array
    {
        $groups = $this->record->orbat['groups'] ?? [];

        $slotTypeIds = collect($groups)
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->pluck('slot_type_id')
            ->filter()
            ->map(fn ($id): int => (int) $id)
            ->unique()
            ->values();

        $slotTypes = SlotType::query()
            ->whereIn('id', $slotTypeIds)
            ->get(['id', 'name', 'image'])
            ->keyBy('id');

        return collect($groups)
            ->map(function (array $group, int $groupIndex) use ($slotTypes): \Filament\Schemas\Components\Section {
                $groupName = trim((string) ($group['name'] ?? '')) ?: 'Grupo sin nombre';
                $slots = $group['slots'] ?? [];

                $slotRows = collect($slots)
                    ->map(function (array $slot, int $slotIndex) use ($groupIndex, $slotTypes): Flex {
                        $slotName = trim((string) ($slot['name'] ?? '')) ?: 'Slot sin nombre';
                        $slotType = $slotTypes->get((int) ($slot['slot_type_id'] ?? 0));
                        $slotTypeName = $slotType?->name ?: 'Sin tipo';

                        $imageMarkup = filled($slotType?->image)
                            ? '<img src="' . e(Storage::disk('public')->url((string) $slotType->image)) . '" alt="" style="width:1em;height:1em;object-fit:contain;flex:none;">'
                            : '';

                        $label = new HtmlString(
                            '<div style="display:flex;align-items:center;gap:.45rem;min-width:0;">'
                            . $imageMarkup
                            . '<div style="min-width:0;">'
                            . '<strong style="display:block;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">' . e($slotName) . '</strong>'
                            . '<span style="display:block;color:#8b95a5;font-size:.75rem;">' . e($slotTypeName) . '</span>'
                            . '</div>'
                            . '</div>'
                        );

                        return Flex::make([
                            Placeholder::make("slot_label_{$groupIndex}_{$slotIndex}")
                                ->hiddenLabel()
                                ->content($label)
                                ->grow(false)
                                ->extraAttributes([
                                    'class' => 'event-orbat-visibility-copy',
                                ]),
                            Flex::make([
                                Placeholder::make("slot_visible_label_{$groupIndex}_{$slotIndex}")
                                    ->hiddenLabel()
                                    ->content('Visible')
                                    ->grow(false),
                                Toggle::make(static::orbatSlotVisibilityField($groupIndex, $slotIndex))
                                    ->hiddenLabel()
                                    ->default((bool) ($slot['visible'] ?? true))
                                    ->grow(false),
                            ])
                                ->dense()
                                ->verticalAlignment(VerticalAlignment::Center)
                                ->grow(false)
                                ->extraAttributes([
                                    'class' => 'event-orbat-visibility-control',
                                ]),
                        ])
                            ->verticalAlignment(VerticalAlignment::Center)
                            ->extraAttributes([
                                'class' => 'event-orbat-visibility-row',
                            ]);
                    })
                    ->all();

                return \Filament\Schemas\Components\Section::make()
                    ->schema([
                        Flex::make([
                            Placeholder::make("group_label_{$groupIndex}")
                                ->hiddenLabel()
                                ->content(new HtmlString('<strong style="font-size:1rem;">' . e($groupName) . '</strong>'))
                                ->grow(false)
                                ->extraAttributes([
                                    'class' => 'event-orbat-visibility-copy',
                                ]),
                            Flex::make([
                                Placeholder::make("group_visible_label_{$groupIndex}")
                                    ->hiddenLabel()
                                    ->content('Visible')
                                    ->grow(false),
                                Toggle::make(static::orbatGroupVisibilityField($groupIndex))
                                    ->hiddenLabel()
                                    ->live()
                                    ->afterStateUpdated(function ($state, Set $set) use ($groupIndex, $slots): void {
                                        foreach (array_keys($slots) as $slotIndex) {
                                            $set(
                                                static::orbatSlotVisibilityField($groupIndex, (int) $slotIndex),
                                                (bool) $state,
                                            );
                                        }
                                    })
                                    ->default((bool) ($group['visible'] ?? true))
                                    ->grow(false),
                            ])
                                ->dense()
                                ->verticalAlignment(VerticalAlignment::Center)
                                ->grow(false)
                                ->extraAttributes([
                                    'class' => 'event-orbat-visibility-control',
                                ]),
                        ])
                            ->verticalAlignment(VerticalAlignment::Center)
                            ->extraAttributes([
                                'class' => 'event-orbat-visibility-group-row',
                            ]),
                        ...$slotRows,
                    ])
                    ->compact()
                    ->extraAttributes([
                        'class' => 'event-orbat-visibility-section',
                    ]);
            })
            ->all();
    }

    protected static function prepareOrbatVisibilityForm(array $orbat): array
    {
        $data = [];

        foreach ($orbat['groups'] ?? [] as $groupIndex => $group) {
            $data[static::orbatGroupVisibilityField($groupIndex)] = (bool) ($group['visible'] ?? true);

            foreach ($group['slots'] ?? [] as $slotIndex => $slot) {
                $data[static::orbatSlotVisibilityField($groupIndex, $slotIndex)] = (bool) ($slot['visible'] ?? true);
            }
        }

        return $data;
    }

    protected static function orbatGroupVisibilityField(int $groupIndex): string
    {
        return "group_visible_{$groupIndex}";
    }

    protected static function orbatSlotVisibilityField(int $groupIndex, int $slotIndex): string
    {
        return "slot_visible_{$groupIndex}_{$slotIndex}";
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
