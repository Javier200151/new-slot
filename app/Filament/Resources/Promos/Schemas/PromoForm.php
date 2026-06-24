<?php

namespace App\Filament\Resources\Promos\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;

class PromoForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('id')
                    ->label('Número de promoción')
                    ->numeric()
                    ->required(),

                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('promos')
                    ->visibility('public')
                    ->required(),
            ]);
    }
}
