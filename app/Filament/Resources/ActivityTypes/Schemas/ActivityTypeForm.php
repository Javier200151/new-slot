<?php

namespace App\Filament\Resources\ActivityTypes\Schemas;

use Filament\Forms\Components\ColorPicker;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ActivityTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

                TextInput::make('description')
                    ->label('Descripción')
                    ->maxLength(255),

                Toggle::make('oficial')
                    ->label('Oficial')
                    ->required(),

                ColorPicker::make('color')
                    ->label('Color'),

                Section::make('Campos y capacidades de la actividad')
                    ->description(
                        'Controla qué información tiene sentido para este tipo. '
                        . 'Los campos desactivados se ocultan en Actividades y Eventos.'
                    )
                    ->columns(3)
                    ->schema([
                        Toggle::make('uses_enemy_factions')
                            ->label('Facciones enemigas')
                            ->default(true),

                        Toggle::make('uses_event_result')
                            ->label('Resultado del evento')
                            ->default(true),

                        Toggle::make('supports_ocap')
                            ->label('OCAP')
                            ->default(true),

                        Toggle::make('supports_respawn')
                            ->label('Respawn')
                            ->default(true),

                        Toggle::make('supports_jip')
                            ->label('JIP')
                            ->default(true),

                        Toggle::make('awards_metopa')
                            ->label('Entrega metopa')
                            ->helperText('Permite asociar una metopa a la actividad y entregarla desde un evento finalizado.')
                            ->default(false),
                    ]),
            ]);
    }
}
