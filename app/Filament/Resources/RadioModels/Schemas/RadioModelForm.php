<?php

namespace App\Filament\Resources\RadioModels\Schemas;

use App\Filament\Forms\BbcodeTextarea;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class RadioModelForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                BbcodeTextarea::make('description')
                    ->label('Descripción')
                    ->columnSpanFull(),

                Toggle::make('channel')
                    ->label('Usa canal')
                    ->default(false)
                    ->required(),

                Toggle::make('block')
                    ->label('Usa bloque')
                    ->default(false)
                    ->required(),

                Toggle::make('frequency')
                    ->label('Usa frecuencia')
                    ->default(false)
                    ->required(),
            ]);
    }
}
