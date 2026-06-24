<?php

namespace App\Filament\Resources\Statuses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class StatusForm
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