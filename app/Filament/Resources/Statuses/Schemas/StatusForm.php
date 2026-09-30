<?php

namespace App\Filament\Resources\Statuses\Schemas;

use App\Models\Status;
use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class StatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Estado de usuario')
                    ->description(fn (?Status $record): string => $record?->is_system
                        ? 'Estado protegido del sistema. El nombre está bloqueado porque puede formar parte de la lógica de negocio.'
                        : 'Los estados creados desde Filament pueden cambiar de nombre y color.'
                    )
                    ->schema([
                        TextInput::make('name')
                            ->label('Nombre')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->disabled(fn (?Status $record): bool => (bool) ($record?->is_system ?? false))
                            ->helperText(fn (?Status $record): ?string => $record?->is_system
                                ? 'Nombre protegido. Solo puede modificarse el color.'
                                : null
                            ),

                        ColorPicker::make('color')
                            ->label('Color')
                            ->required()
                            ->helperText('Se utiliza, entre otros lugares, para identificar el estado en el ORBAT.'),
                    ])
                    ->columns(2),
            ]);
    }
}
