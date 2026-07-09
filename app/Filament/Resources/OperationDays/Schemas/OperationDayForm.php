<?php

namespace App\Filament\Resources\OperationDays\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class OperationDayForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
            ]);
    }
}
