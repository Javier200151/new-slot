<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Army;
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

class EditEvent extends EditRecord
{
    protected static string $resource = EventResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editOrbatVisibility')
                ->label('Editar ORBAT')
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
                                ->dehydrated(false),

                            TextInput::make('army_name')
                                ->label('Ejército')
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
                                        ->dehydrated(false),

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

                    Notification::make()
                        ->title('Visibilidad del ORBAT actualizada.')
                        ->success()
                        ->send();
                }),

            Action::make('restoreOperationOrbat')
                ->label('Recuperar ORBAT original')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('Recuperar ORBAT original')
                ->modalDescription('Se reemplazará el ORBAT del evento por el ORBAT actual del operativo asignado.')
                ->action(function (): void {
                    $this->record->load('operation');

                    $this->record->forceFill([
                        'orbat' => $this->record->operation?->orbat ?? ['groups' => []],
                    ])->save();

                    Notification::make()
                        ->title('ORBAT original recuperado.')
                        ->success()
                        ->send();

                    $this->redirect(EventResource::getUrl('edit', ['record' => $this->record]));
                }),

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    protected static function prepareOrbatVisibilityForm(array $orbat): array
    {
        $groups = $orbat['groups'] ?? [];

        $armyNames = Army::query()
            ->whereIn('id', collect($groups)->pluck('army_id')->filter()->unique())
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
                    'army_name' => $armyNames[(int) ($group['army_id'] ?? 0)] ?? 'Sin ejército',
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
}
