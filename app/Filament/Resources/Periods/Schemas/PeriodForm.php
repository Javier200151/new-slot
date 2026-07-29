<?php

namespace App\Filament\Resources\Periods\Schemas;


use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Filament\Forms\Components\FileUpload;

class PeriodForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
    
                FileUpload::make('ico')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('periods')
                    ->visibility('public')
                    ->preserveFilenames(),
                TextInput::make('description'),
            ]);
    }
}
