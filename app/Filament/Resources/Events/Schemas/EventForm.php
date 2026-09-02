<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\EventStatus;
use App\Models\Operation;
use App\Support\OperationTypeAccess;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
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
                ->label('Tipo de evento')
                ->options(
                    function ($record): array {
                        $action = $record ? 'update' : 'create';
                        $allowedTypeIds =
                            OperationTypeAccess::allowedTypeIds(
                                auth()->user(),
                                'events',
                                $action,
                            );

                        return Operation::query()
                            ->whereIn(
                                'operation_type_id',
                                $allowedTypeIds,
                            )
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    }
                )
                ->searchable()
                ->preload()
                ->live()
                ->afterStateUpdated(
                    function ($state, Get $get, Set $set): void {
                        $operation =
                            Operation::query()
                                ->with('operationStatus')
                                ->find($state);

                        $set(
                            'name',
                            $operation?->name
                        );

                        if ($operation?->editor_ally_id) {
                            $set('multiclans', true);
                        }

                        /*
                        * Si el nuevo operativo está en BORRADOR
                        * y actualmente el evento tenía un estado
                        * público, limpiamos el estado para obligar
                        * al usuario a seleccionar uno válido.
                        */
                        if (
                            $operation?->operationStatus?->name
                            === 'BORRADOR'
                        ) {
                            $currentEventStatusId =
                                $get('event_status_id');

                            if (filled($currentEventStatusId)) {
                                $currentEventStatus =
                                    EventStatus::query()
                                        ->find(
                                            $currentEventStatusId
                                        );

                                if (
                                    in_array(
                                        $currentEventStatus?->name,
                                        [
                                            'ACTIVO',
                                            'FINALIZADO',
                                        ],
                                        true
                                    )
                                ) {
                                    $set(
                                        'event_status_id',
                                        null
                                    );
                                }
                            }
                        }
                    }
                )
                ->disabledOn('edit')
                ->dehydrated()
                ->required(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Toggle::make('multiclans')
                    ->label('Multiclán')
                    ->afterStateHydrated(
                        function (
                            Toggle $component,
                            Get $get
                        ): void {
                            $externalEditor = Operation::query()
                                ->whereKey($get('operation_id'))
                                ->whereNotNull('editor_ally_id')
                                ->exists();

                            if ($externalEditor) {
                                $component->state(true);
                            }
                        }
                    )
                    ->helperText(
                        function (Get $get): string {
                            $externalEditor = Operation::query()
                                ->whereKey($get('operation_id'))
                                ->whereNotNull('editor_ally_id')
                                ->exists();

                            return $externalEditor
                                ? 'Obligatorio: el editor de esta actividad es un aliado.'
                                : 'Actívalo cuando participen otros clanes o comunidades en este evento.';
                        }
                    )
                    ->disabled(
                        fn (Get $get): bool =>
                            Operation::query()
                                ->whereKey($get('operation_id'))
                                ->whereNotNull('editor_ally_id')
                                ->exists()
                    )
                    ->dehydrated()
                    ->inline(false)
                    ->default(false),

                Select::make('event_status_id')
                    ->label('Estado')
                    ->options(
                        function (Get $get): array {
                            $query =
                                EventStatus::query()
                                    ->orderBy('name');

                            $operationId =
                                $get('operation_id');

                            if (filled($operationId)) {
                                $operationStatus =
                                    Operation::query()
                                        ->whereKey($operationId)
                                        ->with('operationStatus')
                                        ->first()
                                        ?->operationStatus
                                        ?->name;

                                /*
                                * Un operativo BORRADOR puede tener
                                * un evento preparado en BORRADOR,
                                * pero ese evento todavía no puede
                                * publicarse.
                                */
                                if (
                                    $operationStatus
                                    === 'BORRADOR'
                                ) {
                                    $query->where(
                                        'name',
                                        'BORRADOR'
                                    );
                                }
                            }

                            return $query
                                ->pluck(
                                    'name',
                                    'id'
                                )
                                ->all();
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->required()
                    ->helperText(
                        function (Get $get): ?string {
                            $operationId =
                                $get('operation_id');

                            if (blank($operationId)) {
                                return null;
                            }

                            $isDraft =
                                Operation::query()
                                    ->whereKey($operationId)
                                    ->whereHas(
                                        'operationStatus',
                                        fn ($query) =>
                                            $query->where(
                                                'name',
                                                'BORRADOR'
                                            )
                                    )
                                    ->exists();

                            if (! $isDraft) {
                                return null;
                            }

                            return 'El operativo está en BORRADOR. '
                                . 'El evento también debe permanecer '
                                . 'en BORRADOR hasta que el operativo '
                                . 'esté activo.';
                        }
                    ),

                

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
                    ->label('Fecha de finalización')
                    ->seconds(false)
                    ->live()
                    ->minDate(
                        fn (Get $get) => $get('date')
                    )
                    ->afterOrEqual('date')
                    ->afterStateUpdated(
                        fn (Get $get, Set $set) =>
                            self::updateDuration($get, $set)
                    ),

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
