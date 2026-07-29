<?php

namespace App\Filament\Resources\Factions\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('army_id')
                    ->label('Ejército')
                    ->relationship('army', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                Select::make('side_id')
                    ->label('Bando')
                    ->relationship('side', 'name')
                    ->searchable()
                    ->preload()
                    ->required(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
            ]);
    }
}
