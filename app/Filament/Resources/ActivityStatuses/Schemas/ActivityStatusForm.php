<?php

namespace App\Filament\Resources\ActivityStatuses\Schemas;

use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\ColorPicker;
use Filament\Schemas\Schema;

class ActivityStatusForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required(),

                ColorPicker::make('color')
                    ->label('Color')
                    ->helperText('Se utilizará para identificar visualmente este estado en el frontend.'),
            ]);
    }
}
