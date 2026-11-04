<?php

namespace App\Filament\Resources\EventStatuses\Schemas;

use App\Models\EventStatus;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Estado de evento')
                ->description(fn (?EventStatus $record): string => $record?->is_system
                    ? 'Estado protegido. Su nombre se utiliza en las funciones de eventos y no puede modificarse.'
                    : 'Puedes crear y editar estados personalizados sin modificar los estados del sistema.'
                )
                ->schema([
                    TextInput::make('name')
                        ->label('Nombre')
                        ->required()
                        ->maxLength(255)
                        ->unique(ignoreRecord: true)
                        ->disabled(fn (?EventStatus $record): bool => (bool) ($record?->is_system ?? false))
                        ->helperText(fn (?EventStatus $record): ?string => $record?->is_system
                            ? 'Nombre protegido para evitar errores en los eventos.'
                            : null),
                ]),
        ]);
    }
}
