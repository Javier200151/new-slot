<?php

namespace App\Filament\Resources\InfrastructureSettings\Schemas;

use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InfrastructureSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Servidores ArmA')
                ->description('Añade, elimina y reordena los servidores que deben aparecer en el estado del pie de página.')
                ->schema([
                    Repeater::make('arma_servers')
                        ->label('Servidores')
                        ->schema([
                            TextInput::make('name')
                                ->label('Nombre')
                                ->required()
                                ->maxLength(120),

                            TextInput::make('host')
                                ->label('IP o dominio')
                                ->maxLength(255),

                            TextInput::make('game_port')
                                ->label('Puerto de juego')
                                ->numeric()
                                ->minValue(1)
                                ->maxValue(65534)
                                ->helperText('El puerto de consulta se calculará automáticamente sumando 1 al puerto de juego.'),
                        ])
                        ->columns(3)
                        ->defaultItems(0)
                        ->reorderable()
                        ->collapsible()
                        ->itemLabel(
                            fn (array $state): ?string => filled($state['name'] ?? null)
                                ? (string) $state['name']
                                : 'Servidor ArmA'
                        )
                        ->columnSpanFull(),
                ]),

            Section::make('TeamSpeak 3 · TSViewer')
                ->schema([
                    Toggle::make('ts3_enabled')
                        ->label('Mostrar TeamSpeak 3 en el estado de servidores')
                        ->columnSpanFull(),

                    TextInput::make('tsviewer_server_id')
                        ->label('ID del servidor en TSViewer')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Introduce el número ID que aparece en la URL de la ficha del servidor en TSViewer.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
