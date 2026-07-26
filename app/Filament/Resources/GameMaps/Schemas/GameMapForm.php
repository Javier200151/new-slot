<?php

namespace App\Filament\Resources\GameMaps\Schemas;

use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class GameMapForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->label('Nombre')
                    ->required()
                    ->maxLength(255),

                Select::make('platform_id')
                    ->label('Plataforma')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),

                Textarea::make('description')
                    ->label('Descripción')
                    ->nullable()
                    ->columnSpanFull(),

                FileUpload::make('image')
                    ->label('Imagen')
                    ->image()
                    ->disk('public')
                    ->directory('maps')
                    ->visibility('public')
                    ->preserveFilenames()
                    ->nullable(),

                TextInput::make('url')
                    ->label('URL')
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                
            ]);
    }
}
