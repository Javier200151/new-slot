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

            Section::make('TeamSpeak 3')
                ->description('Opcional. Usa ServerQuery desde el servidor de NewSlot. Las credenciales nunca se envían al navegador; la contraseña se guarda cifrada.')
                ->schema([
                    Toggle::make('ts3_enabled')
                        ->label('Mostrar usuarios conectados en el footer')
                        ->columnSpanFull(),

                    TextInput::make('ts3_host')
                        ->label('IP o dominio de TeamSpeak')
                        ->maxLength(255),
                    TextInput::make('ts3_query_port')
                        ->label('Puerto ServerQuery')
                        ->numeric()
                        ->default(10011)
                        ->minValue(1)
                        ->maxValue(65535)
                        ->helperText('ServerQuery raw suele usar TCP 10011.'),

                    TextInput::make('ts3_virtual_server_id')
                        ->label('Virtual Server ID (SID)')
                        ->numeric()
                        ->default(1)
                        ->minValue(1),
                    TextInput::make('ts3_query_user')
                        ->label('Usuario ServerQuery')
                        ->maxLength(255),

                    TextInput::make('ts3_query_password')
                        ->label('Contraseña ServerQuery')
                        ->password()
                        ->revealable()
                        ->autocomplete('new-password')
                        ->helperText('Déjalo vacío al editar para conservar la contraseña actual.')
                        ->afterStateHydrated(fn (TextInput $component): TextInput => $component->state(''))
                        ->dehydrated(fn (?string $state): bool => filled($state))
                        ->columnSpanFull(),
                ])
                ->columns(2),
        ]);
    }
}
