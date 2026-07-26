<?php

namespace App\Filament\Resources\EventResults\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventResultForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
