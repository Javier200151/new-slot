<?php

namespace App\Filament\Resources\Sides\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SideForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                FileUpload::make('image')
                    ->image()
                    ->disk('public')
                    ->directory('sides')
                    ->visibility('public')
                    ->preserveFilenames(),
                TextInput::make('description'),
            ]);
    }
}
