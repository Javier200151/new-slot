<?php

namespace App\Filament\Resources\Factions\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class FactionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('side_id')
                    ->label('Bando')
                    ->relationship('side', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),
                FileUpload::make('image')
                    ->label('Imagen')
                    ->disk('public')
                    ->directory('factions')
                    ->visibility('public')
                    ->image(),
                TextInput::make('description')
                    ->label('Descripción'),
            ]);
    }
}
