<?php

namespace App\Filament\Resources\Periods\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                TextInput::make('ico'),
                TextInput::make('description'),
            ]);
    }
}
