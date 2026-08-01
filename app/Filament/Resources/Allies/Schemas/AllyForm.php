<?php

namespace App\Filament\Resources\Allies\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class AllyForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('allies')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->columnSpanFull(),

                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->maxLength(2048)
                    ->placeholder('https://ejemplo.com'),
            ]);
    }
}
