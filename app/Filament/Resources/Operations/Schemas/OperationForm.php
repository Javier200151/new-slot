<?php

namespace App\Filament\Resources\Operations\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\RichEditor;
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
                RichEditor::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),
                Textarea::make('radio')
                    ->label('Radio')
                    ->columnSpanFull(),

                Textarea::make('orbat')
                    ->label('ORBAT')
                    ->columnSpanFull(),

                Toggle::make('ocap')
                    ->label('OCAP')
                    ->required(),
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

                Select::make('enemyFactions')
                    ->label('Facciones enemigas')
                    ->relationship('enemyFactions', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('editor_id')
                    ->label('Editor')
                    ->relationship('editor', 'nick')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Select::make('day_or_night')
                    ->label('Día o noche')
                    ->options([
                        'day' => 'Día',
                        'night' => 'Noche',
                        'both' => 'Ambos',
                    ]),
            ]);
    }
}
