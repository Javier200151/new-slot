<?php

namespace App\Filament\Resources\Events\Pages;

use Illuminate\Validation\ValidationException;
use App\Filament\Resources\Events\EventResource;
use App\Models\Faction;
use App\Models\SlotType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use App\Services\CommunityNotificationService;
use App\Models\EventStatus;
use App\Models\Operation;

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

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

                            TextInput::make('faction_name')
                                ->label('Facción')
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
                    . 'actual del operativo asignado. Si existen usuarios '
                    . 'o aliados ocupando slots que ya no existen o están '
                    . 'ocultos en el nuevo ORBAT, la operación será bloqueada.'
                )
                ->action(function (): void {
                    $this->record->load(
                        'operation'
                    );

                    /*
                    |--------------------------------------------------------------------------
                    | ORBAT que queremos recuperar
                    |--------------------------------------------------------------------------
                    */

                    $newOrbat =
                        $this->record
                            ->operation
                            ?->orbat
                        ?? [
                            'groups' => [],
                        ];


                    /*
                    |--------------------------------------------------------------------------
                    | Comprobar asignaciones
                    |--------------------------------------------------------------------------
                    |
                    | Si el ORBAT actual del operativo ya no contiene alguno
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
        | El operativo de un evento es inmutable
        |--------------------------------------------------------------------------
        |
        | El operativo se selecciona únicamente al crear el evento.
        | Una vez creado, no puede cambiarse ni siquiera manipulando
        | manualmente la petición de Livewire.
        |
        */

        $originalOperationId =
            (int) $this->record->operation_id;

        if (
            array_key_exists('operation_id', $data)
            && (int) $data['operation_id'] !== $originalOperationId
        ) {
            throw ValidationException::withMessages([
                'data.operation_id' =>
                    'El operativo de un evento no puede modificarse '
                    . 'una vez creado. Si necesitas otro operativo, '
                    . 'elimina este evento y crea uno nuevo.',
            ]);
        }

        /*
        * Nos aseguramos además de trabajar siempre
        * con el operativo original del evento.
        */
        $data['operation_id'] =
            $originalOperationId;
            
        /*
        |--------------------------------------------------------------------------
        | Recuperar operativo y estado del evento
        |--------------------------------------------------------------------------
        */

        $operation =
            Operation::query()
                ->with('operationStatus')
                ->find($data['operation_id']);

        $eventStatus =
            EventStatus::query()
                ->find($data['event_status_id']);


        /*
        |--------------------------------------------------------------------------
        | Protección de publicación
        |--------------------------------------------------------------------------
        |
        | Un operativo BORRADOR puede verse públicamente y puede tener
        | un evento BORRADOR preparado.
        |
        | Lo que no permitimos es que dicho evento pase a ACTIVO o
        | FINALIZADO mientras el operativo continúe en BORRADOR.
        |
        */

        if (
            $operation?->operationStatus?->name === 'BORRADOR'
            && $eventStatus?->name !== 'BORRADOR'
        ) {
            throw ValidationException::withMessages([
                'data.event_status_id' =>
                    'No puedes publicar este evento porque '
                    . 'el operativo seleccionado todavía está '
                    . 'en BORRADOR.',
            ]);
        }

        return $data;
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

        $factionNames = Faction::query()
            ->whereIn('id', collect($groups)->pluck('faction_id')->filter()->unique())
            ->pluck('name', 'id');

        $slotTypeNames = SlotType::query()
            ->whereIn(
                'id',
                collect($groups)
                    ->flatMap(fn (array $group): array => $group['slots'] ?? [])
                    ->pluck('slot_type_id')
                    ->filter()
                    ->unique()
            )
            ->pluck('name', 'id');

        return [
            'groups' => collect($groups)
                ->map(fn (array $group): array => [
                    'visible' => (bool) ($group['visible'] ?? true),
                    'name' => $group['name'] ?? '',
                    'faction_name' => $factionNames[(int) ($group['faction_id'] ?? 0)] ?? 'Sin facción',
                    'slots' => collect($group['slots'] ?? [])
                        ->map(fn (array $slot): array => [
                            'visible' => (bool) ($slot['visible'] ?? true),
                            'name' => $slot['name'] ?? '',
                            'slot_type_name' => $slotTypeNames[(int) ($slot['slot_type_id'] ?? 0)] ?? 'Sin tipo',
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
