<?php

namespace App\Filament\Resources\Operations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class OperationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('operation_type_id')
                    ->label('Tipo')
                    ->relationship('operationType', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('operation_status_id')
                    ->label('Estado')
                    ->relationship('operationStatus', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('campaign_id')
                    ->label('Campaña')
                    ->relationship('campaign', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('platform_id')
                    ->label('Plataforma')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('date')
                    ->label('Fecha')
                    ->required(),
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                FileUpload::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->directory('operations')
                    ->visibility('public')
                    ->image(),
                Textarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Textarea::make('radio')
                    ->label('Radio')
                    ->columnSpanFull(),
                Toggle::make('ocap')
                    ->label('OCAP')
                    ->required(),
                TextInput::make('ocap_url')
                    ->label('URL OCAP')
                    ->url(),
                Toggle::make('respawn')
                    ->label('Respawn')
                    ->required(),
                Toggle::make('jip')
                    ->label('JIP')
                    ->required(),

                Select::make('day_id')
                    ->label('Día')
                    ->relationship('day', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('pbo')
                    ->label('PBO'),
                Textarea::make('addons')
                    ->label('Addons')
                    ->columnSpanFull(),
                //TextInput::make('created_by')
                 //   ->numeric(),
                //TextInput::make('updated_by')
                //    ->numeric(),

                Select::make('map_id')
                    ->label('Mapa')
                    ->relationship('map', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('period_id')
                    ->label('Periodo')
                    ->relationship('period', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('faction_id')
                    ->label('Facción')
                    ->relationship('faction', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('editor_id')
                    ->label('Editor')
                    ->relationship('editor', 'nick')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('duration_minutes')
                    ->label('Duración en minutos')
                    ->numeric(),
                Select::make('day_or_night')
                    ->label('Día o noche')
                    ->options([
                        'day' => 'Día',
                        'night' => 'Noche',
                    ]),
                TextInput::make('side')
                    ->label('Bando'),
            ]);
    }
}
