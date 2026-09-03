<?php

namespace App\Filament\Resources\CommunityRoulettePhrases\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class CommunityRoulettePhraseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Celebración del ganador')
                ->description('Una de las frases activas se elige al azar al iniciar la tirada. El texto queda guardado en el histórico de esa sala aunque después lo edites.')
                ->schema([
                    Textarea::make('text')
                        ->label('Frase')
                        ->required()
                        ->rows(4)
                        ->maxLength(500)
                        ->columnSpanFull(),

                    Toggle::make('active')
                        ->label('Disponible para nuevos sorteos')
                        ->default(true),

                    TextInput::make('sort_order')
                        ->label('Orden')
                        ->numeric()
                        ->minValue(0)
                        ->maxValue(65535)
                        ->default(100)
                        ->required(),
                ])
                ->columns(2),
        ]);
    }
}
