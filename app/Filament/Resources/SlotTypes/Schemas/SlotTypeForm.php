<?php

namespace App\Filament\Resources\SlotTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SlotTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                TextInput::make('description')
                    ->label('Descripción')
                    ->required()
                    ->maxLength(255),
            ]);
    }
}
