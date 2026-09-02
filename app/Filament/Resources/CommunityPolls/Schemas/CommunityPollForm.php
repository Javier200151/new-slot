<?php

namespace App\Filament\Resources\CommunityPolls\Schemas;

use App\Models\CommunityPoll;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunityPollForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contenido')
                ->columns(2)
                ->schema([
                    TextInput::make('title')
                        ->label('Título')
                        ->required()
                        ->maxLength(180)
                        ->columnSpanFull(),

                    Textarea::make('description')
                        ->label('Descripción')
                        ->rows(5)
                        ->maxLength(10000)
                        ->columnSpanFull(),

                    Toggle::make('is_published')
                        ->label('Publicada')
                        ->default(false),

                    DateTimePicker::make('starts_at')
                        ->label('Inicio')
                        ->seconds(false),

                    DateTimePicker::make('ends_at')
                        ->label('Cierre')
                        ->seconds(false)
                        ->after('starts_at'),
                ]),

            Section::make('Reglas del voto')
                ->description('Define cómo puede votar cada miembro y qué puede hacer después de enviar su voto.')
                ->columns(3)
                ->schema([
                    Select::make('selection_mode')
                        ->label('Tipo de selección')
                        ->options([
                            CommunityPoll::MODE_SINGLE => 'Una sola opción',
                            CommunityPoll::MODE_MULTIPLE => 'Múltiples opciones',
                        ])
                        ->default(CommunityPoll::MODE_SINGLE)
                        ->required(),

                    TextInput::make('min_choices')
                        ->label('Mínimo de opciones')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->default(1)
                        ->required()
                        ->helperText('En voto único siempre se aplica 1.'),

                    TextInput::make('max_choices')
                        ->label('Máximo de opciones')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(50)
                        ->placeholder('Sin límite')
                        ->helperText('Solo se usa en votaciones de selección múltiple.'),

                    Toggle::make('allow_vote_change')
                        ->label('Permitir cambiar el voto')
                        ->default(true)
                        ->helperText('Si se desactiva, el primer voto enviado queda bloqueado.'),

                    Toggle::make('allow_abstain')
                        ->label('Permitir abstención')
                        ->default(false)
                        ->helperText('Añade la posibilidad de registrar una abstención sin elegir opciones.'),

                    Toggle::make('randomize_options')
                        ->label('Orden aleatorio de opciones')
                        ->default(false)
                        ->helperText('Cada usuario puede ver las opciones en un orden diferente.'),
                ]),

            Section::make('Privacidad y resultados')
                ->columns(3)
                ->schema([
                    Toggle::make('is_anonymous')
                        ->label('Voto anónimo')
                        ->default(false)
                        ->helperText('Oculta públicamente quién ha votado cada opción.'),

                    Toggle::make('show_voter_names')
                        ->label('Mostrar nombres de votantes')
                        ->default(false)
                        ->helperText('Solo tiene efecto si la votación no es anónima y los resultados son visibles.'),

                    Toggle::make('show_participation')
                        ->label('Mostrar participación')
                        ->default(true)
                        ->helperText('Muestra cuántos miembros han votado.'),

                    Select::make('results_visibility')
                        ->label('Visibilidad de resultados')
                        ->options([
                            CommunityPoll::RESULTS_ALWAYS => 'Siempre visibles',
                            CommunityPoll::RESULTS_AFTER_VOTE => 'Después de votar',
                            CommunityPoll::RESULTS_AFTER_CLOSE => 'Solo al cerrar',
                            CommunityPoll::RESULTS_HIDDEN => 'Ocultos',
                        ])
                        ->default(CommunityPoll::RESULTS_ALWAYS)
                        ->required(),

                    TextInput::make('quorum_percent')
                        ->label('Quórum mínimo (%)')
                        ->numeric()
                        ->minValue(1)
                        ->maxValue(100)
                        ->placeholder('Sin quórum')
                        ->helperText('Porcentaje de miembros ACTIVOS que deben participar. Es informativo; no cierra la votación.'),
                ]),

            Repeater::make('options')
                ->label('Opciones de voto')
                ->relationship()
                ->schema([
                    TextInput::make('label')
                        ->label('Opción')
                        ->required()
                        ->maxLength(180),
                    TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->default(10)
                        ->required(),
                ])
                ->columns(2)
                ->minItems(2)
                ->defaultItems(2)
                ->reorderable()
                ->columnSpanFull(),
        ]);
    }
}
