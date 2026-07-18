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

                Select::make('platform_id')
                    ->label('Plataforma')
                    ->relationship('platform', 'name')
                    ->searchable()
                    ->preload()
                    ->nullable(),
            ]);
    }
}
