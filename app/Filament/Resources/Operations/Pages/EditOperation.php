<?php

namespace App\Filament\Resources\Operations\Pages;

use App\Filament\Resources\Operations\OperationResource;
use App\Models\Army;
use App\Models\SlotType;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditOperation extends EditRecord
{
    protected static string $resource = OperationResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('editOrbat')
                ->label('Editar ORBAT')
                ->modalHeading('Editor de ORBAT')
                ->modalSubmitActionLabel('Guardar ORBAT')
                ->modalWidth('7xl')
                ->fillForm(fn (): array => $this->record->orbat ?? ['groups' => []])
                ->form([
                    Repeater::make('groups')
                        ->label('Grupos')
                        ->columns(3)
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(255),

                            Select::make('army_id')
                                ->label('Ejército')
                                ->options(fn (): array => Army::query()
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),

                            Toggle::make('visible')
                                ->label('Visible')
                                ->default(true),

                            Repeater::make('slots')
                                ->label('Slots')
                                ->columns(2)
                                ->schema([
                                    Hidden::make('slot_key')
                                        ->default(fn (): string => (string) Str::ulid()),

                                    TextInput::make('name')
                                        ->label('Nombre')
                                        ->required()
                                        ->maxLength(255),

                                    Select::make('slot_type_id')
                                        ->label('Tipo de slot')
                                        ->options(fn (): array => SlotType::query()
                                            ->orderBy('name')
                                            ->pluck('name', 'id')
                                            ->all())
                                        ->searchable()
                                        ->required(),
                                ])
                                ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                                ->reorderableWithButtons()
                                ->collapsible()
                                ->default([])
                                ->addActionLabel('Añadir slot')
                                ->columnSpanFull(),
                        ])
                        ->itemLabel(fn (array $state): ?string => $state['name'] ?? null)
                        ->reorderableWithButtons()
                        ->collapsible()
                        ->default([])
                        ->addActionLabel('Añadir grupo')
                        ->columnSpanFull(),
                ])
                ->action(function (array $data): void {
                    $groups = collect($data['groups'] ?? [])
                        ->map(function (array $group): array {
                            $slots = collect($group['slots'] ?? [])
                                ->map(fn (array $slot): array => [
                                    'slot_key' => $slot['slot_key'] ?: (string) Str::ulid(),
                                    'name' => $slot['name'] ?? '',
                                    'slot_type_id' => isset($slot['slot_type_id']) ? (int) $slot['slot_type_id'] : null,
                                ])
                                ->values()
                                ->all();

                            return [
                                'name' => $group['name'] ?? '',
                                'army_id' => isset($group['army_id']) ? (int) $group['army_id'] : null,
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

            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
