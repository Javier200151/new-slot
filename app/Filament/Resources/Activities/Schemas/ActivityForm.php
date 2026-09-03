<?php

namespace App\Filament\Resources\Activities\Schemas;

use App\Models\GameMap;
use App\Models\ActivityType;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityEditorSelection;
use App\Support\ActivityTypeConfiguration;
use App\Support\FactionOptionLabel;
use App\Models\Metopa;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Html;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;
use App\Models\Country;
use App\Models\Faction;
use App\Models\Army;
use Filament\Actions\Action;
use Filament\Forms\Components\Hidden;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

                Select::make('activity_type_id')
                    ->label('Tipo de actividad')
                    ->options(
                        function ($record): array {
                            $action = $record ? 'update' : 'create';
                            $allowedTypeIds =
                                ActivityTypeAccess::allowedTypeIds(
                                    auth()->user(),
                                    'activities',
                                    $action,
                                );

                            return ActivityType::query()
                                ->whereIn('id', $allowedTypeIds)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all();
                        }
                    )
                    ->searchable()
                    ->preload()
                    ->live()
                    ->afterStateUpdated(
                        function ($state, Set $set): void {
                            $type = ActivityTypeConfiguration::find($state);

                            if (! $type) {
                                return;
                            }

                            if (! $type->supportsOcap()) {
                                $set('ocap', false);
                            }

                            if (! $type->supportsRespawn()) {
                                $set('respawn', false);
                            }

                            if (! $type->supportsJip()) {
                                $set('jip', false);
                            }

                            if (! $type->usesEnemyFactions()) {
                                $set('enemyFactions', []);
                            }

                            if (! $type->awardsMetopa()) {
                                $set('metopa_id', null);
                            }
                        }
                    )
                    ->required(),

                Select::make('activity_status_id')
                    ->label('Estado')
                    ->relationship('activityStatus', 'name')
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
                    ->directory('activities')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->image(),
                

                Section::make('Opciones')
                    ->inlineLabel(false)
                    ->columns(3)
                    ->visible(
                        function (Get $get): bool {
                            $type = ActivityTypeConfiguration::find(
                                $get('activity_type_id')
                            );

                            return $type !== null
                                && (
                                    $type->supportsOcap()
                                    || $type->supportsRespawn()
                                    || $type->supportsJip()
                                );
                        }
                    )
                    ->schema([
                        Toggle::make('ocap')
                            ->inline(false)
                            ->label('OCAP')
                            ->visible(
                                fn (Get $get): bool =>
                                    ActivityTypeConfiguration::find($get('activity_type_id'))?->supportsOcap()
                                    ?? false
                            ),
                        Toggle::make('respawn')
                            ->inline(false)
                            ->label('Respawn')
                            ->visible(
                                fn (Get $get): bool =>
                                    ActivityTypeConfiguration::find($get('activity_type_id'))?->supportsRespawn()
                                    ?? false
                            ),
                        Toggle::make('jip')
                            ->inline(false)
                            ->label('JIP')
                            ->visible(
                                fn (Get $get): bool =>
                                    ActivityTypeConfiguration::find($get('activity_type_id'))?->supportsJip()
                                    ?? false
                            ),
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

                Hidden::make('enemy_faction_country_filter')
                    ->dehydrated(false),

                Hidden::make('enemy_faction_army_filter')
                    ->dehydrated(false),

                Select::make('enemyFactions')
                    ->label('Facciones enemigas')

                    /*
                    |--------------------------------------------------------------------------
                    | Relación que realmente se guarda
                    |--------------------------------------------------------------------------
                    */

                    ->relationship(
                        'enemyFactions',
                        'name'
                    )

                    /*
                    |--------------------------------------------------------------------------
                    | Opciones filtradas
                    |--------------------------------------------------------------------------
                    */

                    ->options(
                        function (Get $get): array {

                            $countryId =
                                $get(
                                    'enemy_faction_country_filter'
                                );

                            $armyId =
                                $get(
                                    'enemy_faction_army_filter'
                                );

                            $selectedIds =
                                collect(
                                    $get('enemyFactions')
                                    ?? []
                                )
                                    ->filter()
                                    ->map(
                                        fn ($id): int =>
                                            (int) $id
                                    )
                                    ->values()
                                    ->all();

                            $query =
                                Faction::query();


                            /*
                            |--------------------------------------------------------------------------
                            | Aplicar filtros
                            |--------------------------------------------------------------------------
                            */

                            if (
                                filled($countryId)
                                || filled($armyId)
                            ) {
                                $query->where(
                                    function ($query) use (
                                        $countryId,
                                        $armyId,
                                        $selectedIds
                                    ): void {

                                        $query->where(
                                            function ($query) use (
                                                $countryId,
                                                $armyId
                                            ): void {

                                                /*
                                                * País:
                                                *
                                                * Faction
                                                *    ↓
                                                * Army
                                                *    ↓
                                                * Country
                                                */

                                                if (filled($countryId)) {
                                                    $query->whereHas(
                                                        'army',
                                                        fn ($armyQuery) =>
                                                            $armyQuery->where(
                                                                'country_id',
                                                                $countryId
                                                            )
                                                    );
                                                }


                                                /*
                                                * Ejército.
                                                */

                                                if (filled($armyId)) {
                                                    $query->where(
                                                        'army_id',
                                                        $armyId
                                                    );
                                                }
                                            }
                                        );


                                        /*
                                        * Conservamos las facciones
                                        * que ya estaban seleccionadas,
                                        * aunque cambies el filtro.
                                        */

                                        if ($selectedIds !== []) {
                                            $query->orWhereIn(
                                                'factions.id',
                                                $selectedIds
                                            );
                                        }
                                    }
                                );
                            }

                            return $query
                                ->with([
                                    'side',
                                    'army.country',
                                ])
                                ->orderBy('name')
                                ->get()
                                ->mapWithKeys(
                                    fn (Faction $faction): array => [
                                        $faction->id =>
                                            FactionOptionLabel::make(
                                                $faction
                                            ),
                                    ]
                                )
                                ->all();
                        }
                    )
                    ->allowHtml()

                    /*
                    |--------------------------------------------------------------------------
                    | Botón de filtro
                    |--------------------------------------------------------------------------
                    */

                    ->suffixAction(
                        Action::make(
                            'filterEnemyFactions'
                        )
                            ->label(
                                'Filtrar facciones'
                            )
                            ->icon(
                                'heroicon-o-funnel'
                            )
                            ->iconButton()
                            ->tooltip(
                                'Filtrar por país o ejército'
                            )
                            ->color(
                                function (
                                    Get $schemaGet
                                ): string {
                                    return (
                                        filled(
                                            $schemaGet(
                                                'enemy_faction_country_filter'
                                            )
                                        )
                                        || filled(
                                            $schemaGet(
                                                'enemy_faction_army_filter'
                                            )
                                        )                                  
                                    )
                                        ? 'primary'
                                        : 'gray';
                                }
                            )

                            /*
                            |--------------------------------------------------------------------------
                            | Valores actuales al abrir
                            |--------------------------------------------------------------------------
                            */

                            ->fillForm(
                                function (
                                    Get $schemaGet
                                ): array {
                                    return [
                                        'country_id' =>
                                            $schemaGet(
                                                'enemy_faction_country_filter'
                                            ),

                                        'army_id' =>
                                            $schemaGet(
                                                'enemy_faction_army_filter'
                                            ),
                                    ];
                                }
                            )

                            /*
                            |--------------------------------------------------------------------------
                            | Formulario del embudo
                            |--------------------------------------------------------------------------
                            */

                            ->schema([

                                Select::make(
                                    'country_id'
                                )
                                    ->label('País')
                                    ->options(
                                        fn (): array =>
                                            Country::query()
                                                ->orderBy('name')
                                                ->pluck(
                                                    'name',
                                                    'id'
                                                )
                                                ->all()
                                    )
                                    ->searchable()
                                    ->preload()
                                    ->placeholder(
                                        'Todos los países'
                                    )
                                    ->live()
                                    ->afterStateUpdated(
                                        fn (Set $set) =>
                                            $set('army_id', null)
                                    ),

                                Select::make(
                                    'army_id'
                                )
                                    ->label('Ejército')
                                    ->options(
                                        function (Get $get): array {
                                            $query =
                                                Army::query()
                                                    ->orderBy('name');

                                            if (
                                                filled(
                                                    $get('country_id')
                                                )
                                            ) {
                                                $query->where(
                                                    'country_id',
                                                    $get('country_id')
                                                );
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
                                    ->placeholder(
                                        'Todos los ejércitos'
                                    ),
                            ])

                            ->modalHeading(
                                'Filtrar facciones'
                            )
                            ->modalDescription(
                                'Puedes filtrar por país, por ejército o por ambos.'
                            )
                            ->modalSubmitActionLabel(
                                'Aplicar filtros'
                            )
                            ->modalWidth('md')

                            /*
                            |--------------------------------------------------------------------------
                            | Aplicar
                            |--------------------------------------------------------------------------
                            */

                            ->action(
                                function (
                                    array $data,
                                    Set $schemaSet
                                ): void {

                                    $schemaSet(
                                        'enemy_faction_country_filter',
                                        $data['country_id']
                                        ?? null
                                    );

                                    $schemaSet(
                                        'enemy_faction_army_filter',
                                        $data['army_id']
                                        ?? null
                                    );
                                }
                            )
                    )

                    ->multiple()
                    ->searchable()
                    ->preload()
                    ->visible(
                        fn (Get $get): bool =>
                            ActivityTypeConfiguration::find($get('activity_type_id'))?->usesEnemyFactions()
                            ?? false
                    )
                    ->nullable(),

                Select::make('metopa_id')
                    ->label('Metopa del curso')
                    ->options(
                        fn (): array => Metopa::query()
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all()
                    )
                    ->searchable()
                    ->preload()
                    ->visible(
                        fn (Get $get): bool =>
                            ActivityTypeConfiguration::find($get('activity_type_id'))?->awardsMetopa()
                            ?? false
                    )
                    ->helperText('Se propondrá automáticamente al finalizar un evento de esta actividad.')
                    ->nullable(),

                Select::make('editor_choice')
                    ->label('Editor')
                    ->options(
                        fn (): array =>
                            ActivityEditorSelection::options()
                    )
                    ->searchable()
                    ->preload()
                    ->nullable()
                    ->helperText(
                        'Puedes seleccionar un miembro SQA o un aliado. ' .
                        'Si el editor es un aliado, sus eventos serán multiclán.'
                    ),

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
    private static function factionOptionLabel(
        Faction $faction
    ): string {
        $country =
            $faction->army?->country?->name
            ?? 'Sin país';

        $side =
            $faction->side?->name
            ?? 'Sin bando';

        $army =
            $faction->army?->name
            ?? 'Sin ejército';

        return sprintf(
            '%s · %s · %s · %s',
            $faction->name,
            $country,
            $side,
            $army,
        );
    }
}
