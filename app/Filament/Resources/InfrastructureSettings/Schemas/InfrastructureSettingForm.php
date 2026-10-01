<?php

namespace App\Filament\Resources\InfrastructureSettings\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class InfrastructureSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Servidores de juego')
                ->description('Configura el host y el puerto de consulta A2S/Steam Query. El footer usa estos datos para mostrar el estado verde o rojo.')
                ->schema([
                    TextInput::make('arma3_academy_host')
                        ->label('ArmA 3 Academia · IP o dominio')
                        ->maxLength(255),
                    TextInput::make('arma3_academy_query_port')
                        ->label('ArmA 3 Academia · puerto A2S')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->helperText('Normalmente es el puerto de juego + 1.'),

                    TextInput::make('arma3_operations_host')
                        ->label('ArmA 3 Operativos · IP o dominio')
                        ->maxLength(255),
                    TextInput::make('arma3_operations_query_port')
                        ->label('ArmA 3 Operativos · puerto A2S')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535),

                    TextInput::make('reforger_academy_host')
                        ->label('ArmA Reforger Academia · IP o dominio')
                        ->maxLength(255),
                    TextInput::make('reforger_academy_query_port')
                        ->label('ArmA Reforger Academia · puerto A2S')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535)
                        ->helperText('Reforger usa A2S; el puerto por defecto documentado es 17777.'),

                    TextInput::make('reforger_operations_host')
                        ->label('ArmA Reforger Operativos · IP o dominio')
                        ->maxLength(255),
                    TextInput::make('reforger_operations_query_port')
                        ->label('ArmA Reforger Operativos · puerto A2S')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(65535),
                ])
                ->columns(2),

            Section::make('TeamSpeak 3 · TSViewer')
                ->schema([
                    Toggle::make('ts3_enabled')
                        ->label('Mostrar TeamSpeak 3 en el estado de servidores')
                        ->columnSpanFull(),

                    TextInput::make('tsviewer_server_id')
                        ->label('ID del servidor en TSViewer')
                        ->numeric()
                        ->minValue(1)
                        ->helperText('Registra el TS3 en TSViewer.com y copia el número de su URL, por ejemplo: ...?ID=1121394&page=ts_viewer.')
                        ->columnSpanFull(),
                ]),
        ]);
    }
}
