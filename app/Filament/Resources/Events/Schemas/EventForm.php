<?php

namespace App\Filament\Resources\Events\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class EventForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('operation_id')
                    ->label('Operativo')
                    ->relationship('operation', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('event_status_id')
                    ->label('Estado')
                    ->relationship('eventStatus', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                DateTimePicker::make('date')
                    ->label('Fecha')
                    ->seconds(false)
                    ->required(),

                TextInput::make('duration')
                    ->label('Duración')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('min')
                    ->nullable(),

                TextInput::make('ocap_url')
                    ->label('URL OCAP')
                    ->url()
                    ->maxLength(255),

                Section::make('ORBAT')
                    ->schema([
                        Html::make(fn ($record) => $record?->getOrbatSummaryHtml()),
                    ])
                    ->hidden(fn ($record): bool => blank($record?->orbat['groups'] ?? []))
                    ->columnSpanFull(),
            ]);
    }
}
