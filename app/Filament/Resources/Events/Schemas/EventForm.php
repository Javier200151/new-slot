<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\Operation;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Support\Carbon;

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
                    ->live()
                    ->afterStateUpdated(function ($state, Set $set): void {
                        $set('name', Operation::query()->whereKey($state)->value('name'));
                    })
                    ->required(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Select::make('event_status_id')
                    ->label('Estado')
                    ->relationship('eventStatus', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                

                DateTimePicker::make('date')
                    ->label('Fecha')
                    ->seconds(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set): null => static::updateDuration($get, $set))
                    ->required(),

                Select::make('event_result_id')
                    ->label('Resultado')
                    ->relationship('eventResult', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),   

                DateTimePicker::make('end_date')
                    ->label('Fecha fin')
                    ->seconds(false)
                    ->live()
                    ->afterStateUpdated(fn (Get $get, Set $set): null => static::updateDuration($get, $set))
                    ->nullable(),

                TextInput::make('ocap_url')
                    ->label('URL OCAP')
                    ->url()
                    ->maxLength(255)
                    ->visible(fn (Get $get): bool => (bool) Operation::query()
                        ->whereKey($get('operation_id'))
                        ->value('ocap')),

                TextInput::make('duration')
                    ->label('Duración')
                    ->numeric()
                    ->minValue(0)
                    ->suffix('min')
                    ->nullable(),

                

                Section::make('ORBAT')
                    ->schema([
                        Html::make(fn ($record) => $record?->getOrbatSummaryHtml()),
                    ])
                    ->hidden(fn ($record): bool => blank($record?->orbat['groups'] ?? []))
                    ->columnSpanFull(),
            ]);
    }

    protected static function updateDuration(Get $get, Set $set): null
    {
        $startDate = $get('date');
        $endDate = $get('end_date');

        if (blank($startDate) || blank($endDate)) {
            return null;
        }

        $minutes = Carbon::parse($startDate)->diffInMinutes(Carbon::parse($endDate), false);

        $set('duration', max(0, (int) $minutes));

        return null;
    }
}
