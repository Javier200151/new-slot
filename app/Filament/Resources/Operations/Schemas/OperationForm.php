<?php

namespace App\Filament\Resources\Operations\Schemas;

use App\Models\GameMap;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;

class OperationForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

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
                    ->live()
                    ->afterStateUpdated(fn (Set $set) => $set('map_id', null))
                    ->required(),

                Select::make('days')
                ->label('Días')
                ->relationship('days', 'name')
                ->multiple()
                ->searchable()
                ->preload()
                ->helperText(
                    'Déjalo vacío si puede jugarse cualquier día.'
                ),
                
                FileUpload::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->directory('operations')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->image(),
                

                Section::make('Opciones')
                    ->inlineLabel(false)
                    ->columns(3)
                    ->schema([
                        Toggle::make('ocap')
                            ->inline(false)
                            ->label('OCAP'),
                        Toggle::make('respawn')
                            ->inline(false)
                            ->label('Respawn'),
                        Toggle::make('jip')
                            ->inline(false)
                            ->label('JIP'),
                    ]),

                // Toggle::make('ocap')
                //     ->label('OCAP')
                //     ->required(),
                // Toggle::make('respawn')
                //     ->label('Respawn')
                //     ->required(),
                // Toggle::make('jip')
                //     ->label('JIP')
                //     ->required(),

                Select::make('map_id')
                    ->label('Mapa')
                    ->options(fn (Get $get): array => GameMap::query()
                        ->where('platform_id', $get('platform_id'))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable()
                    ->preload()
                    ->disabled(fn (Get $get): bool => blank($get('platform_id')))
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
                TextInput::make('pbo')
                    ->label('PBO'),                                        
     

                Section::make('Descripción')
                    ->schema([
                        Html::make(fn ($record) => $record?->getDescriptionSummaryHtml()),
                    ])
                    ->columnSpanFull(),

                Section::make('ORBAT')
                    ->schema([
                        Html::make(fn ($record) => $record?->getOrbatSummaryHtml()),
                    ])
                    //->hidden(fn ($record): bool => blank($record?->orbat['groups'] ?? []))
                    ->columnSpanFull(),

                Section::make('Radio')
                    ->schema([
                        Html::make(fn ($record) => $record?->getRadioSummaryHtml()),
                    ])
                    ->columnSpanFull(),

                Section::make('Addons')
                    ->schema([
                        Html::make(fn ($record) => $record?->getAddonsSummaryHtml()),
                    ])
                    ->columnSpanFull(),
                

                


                
                //TextInput::make('created_by')
                 //   ->numeric(),
                //TextInput::make('updated_by')
                //    ->numeric(),


            ]);
    }
}
