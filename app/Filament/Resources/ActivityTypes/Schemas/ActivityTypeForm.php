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

                Section::make('Campos de la actividad')
                    ->description('Activa únicamente los datos que deben aparecer al crear y editar actividades de este tipo.')
                    ->columns(3)
                    ->schema([
                        Toggle::make('uses_campaign')->label('Campaña')->default(true),
                        Toggle::make('uses_days')->label('Días habituales')->default(true),
                        Toggle::make('uses_image')->label('Imagen')->default(true),
                        Toggle::make('uses_map')->label('Mapa')->default(true),
                        Toggle::make('uses_period')->label('Periodo')->default(true),
                        Toggle::make('uses_editor')->label('Editor')->default(true),
                        Toggle::make('uses_day_or_night')->label('Día / noche')->default(true),
                        Toggle::make('uses_pbo')->label('PBO')->default(true),
                        Toggle::make('uses_enemy_factions')->label('Facciones enemigas')->default(true),
                        Toggle::make('uses_briefing')->label('Briefing base')->default(true),
                        Toggle::make('uses_orbat')->label('ORBAT')->default(true),
                        Toggle::make('uses_radio')->label('Comunicaciones')->default(true),
                        Toggle::make('uses_addons')->label('Addons')->default(true),
                        Toggle::make('awards_metopa')
                            ->label('Entrega metopa')
                            ->helperText('Permite asociar una metopa a la actividad y entregarla desde un evento finalizado.')
                            ->default(false),
                    ]),

                Section::make('Capacidades del evento')
                    ->description('Controla qué opciones estarán disponibles en los eventos creados a partir de este tipo de actividad.')
                    ->columns(3)
                    ->schema([
                        Toggle::make('uses_event_result')->label('Resultado del evento')->default(true),
                        Toggle::make('supports_ocap')->label('OCAP')->default(true),
                        Toggle::make('supports_respawn')->label('Respawn')->default(true),
                        Toggle::make('supports_jip')->label('JIP')->default(true),
                        Toggle::make('uses_multiclans')->label('Multiclán')->default(true),
                        Toggle::make('uses_reservations')->label('Reservas')->default(true),
                        Toggle::make('uses_event_end_date')->label('Fecha de finalización / duración')->default(true),
                        Toggle::make('uses_event_briefing')
                            ->label('Briefing adicional del evento')
                            ->helperText('Permite añadir información específica de una fecha sin modificar el briefing base de la actividad.')
                            ->default(true),
                    ]),
            ]);
    }
}
