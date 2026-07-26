<?php

namespace App\Filament\Resources\OperationTypes\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Filament\Forms\Components\ColorPicker;

class OperationTypeForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Toggle::make('oficial')
                    ->required(),
                ColorPicker::make('color'),
            ]);
    }
}
