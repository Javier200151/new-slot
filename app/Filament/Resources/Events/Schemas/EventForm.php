<?php

namespace App\Filament\Resources\Events\Schemas;

use App\Models\EventStatus;
use App\Models\Activity;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityTypeConfiguration;
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
                Select::make('activity_id')
                ->label('Tipo de evento')
                ->options(
                    function ($record): array {
                        $action = $record ? 'update' : 'create';
                        $allowedTypeIds =
                            ActivityTypeAccess::allowedTypeIds(
                                auth()->user(),
                                'events',
                                $action,
                            );

                        return Activity::query()
                            ->whereIn(
                                'activity_type_id',
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
                        $activity =
                            Activity::query()
                                ->with(['activityStatus', 'activityType'])
                                ->find($state);

                        $set(
                            'name',
                            $activity?->name
                        );

                        if ($activity?->editor_ally_id) {
                            $set('multiclans', true);
                        }

                        if (! ($activity?->activityType?->usesEventResult() ?? true)) {
                            $set('event_result_id', null);
                        }

                        if (
                            ! ($activity?->activityType?->supportsOcap() ?? true)
                            || ! $activity?->ocap
                        ) {
                            $set('ocap_url', null);
                        }

                        /*
                        * Si el nuevo actividad está en BORRADOR
                        * y actualmente el evento tenía un estado
                        * público, limpiamos el estado para obligar
                        * al usuario a seleccionar uno válido.
                        */
                        if (
                            $activity?->activityStatus?->name
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
                            $externalEditor = Activity::query()
                                ->whereKey($get('activity_id'))
                                ->whereNotNull('editor_ally_id')
                                ->exists();

                            if ($externalEditor) {
                                $component->state(true);
                            }
                        }
                    )
                    ->helperText(
                        function (Get $get): string {
                            $externalEditor = Activity::query()
                                ->whereKey($get('activity_id'))
                                ->whereNotNull('editor_ally_id')
                                ->exists();

                            return $externalEditor
                                ? 'Obligatorio: el editor de esta actividad es un aliado.'
                                : 'Actívalo cuando participen otros clanes o comunidades en este evento.';
                        }
                    )
                    ->disabled(
                        fn (Get $get): bool =>
                            Activity::query()
                                ->whereKey($get('activity_id'))
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

                            $activityId =
                                $get('activity_id');

                            if (filled($activityId)) {
                                $activityStatus =
                                    Activity::query()
                                        ->whereKey($activityId)
                                        ->with('activityStatus')
                                        ->first()
                                        ?->activityStatus
                                        ?->name;

                                /*
                                * Un actividad BORRADOR puede tener
                                * un evento preparado en BORRADOR,
                                * pero ese evento todavía no puede
                                * publicarse.
                                */
                                if (
                                    $activityStatus
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
                            $activityId =
                                $get('activity_id');

                            if (blank($activityId)) {
                                return null;
                            }

                            $isDraft =
                                Activity::query()
                                    ->whereKey($activityId)
                                    ->whereHas(
                                        'activityStatus',
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

                            return 'La actividad está en BORRADOR. '
                                . 'El evento también debe permanecer '
                                . 'en BORRADOR hasta que la actividad '
                                . 'esté activa.';
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
                    ->visible(
                        function (Get $get): bool {
                            $typeId = Activity::query()
                                ->whereKey($get('activity_id'))
                                ->value('activity_type_id');

                            return ActivityTypeConfiguration::find($typeId)?->usesEventResult()
                                ?? false;
                        }
                    )
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
                    ->visible(
                        function (Get $get): bool {
                            $activity = Activity::query()
                                ->with('activityType')
                                ->find($get('activity_id'));

                            return (bool) $activity?->ocap
                                && ($activity?->activityType?->supportsOcap() ?? false);
                        }
                    ),

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
