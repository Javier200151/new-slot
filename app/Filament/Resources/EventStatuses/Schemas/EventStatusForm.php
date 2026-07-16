<?php

namespace App\Filament\Resources\EventStatuses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class EventStatusForm
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
