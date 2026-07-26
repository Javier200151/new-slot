<?php

namespace App\Filament\Resources\Streamers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class StreamerForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Usuario')
                    ->relationship('user', 'nick')
                    ->searchable()
                    ->preload()
                    ->required()
                    ->unique(ignoreRecord: true),

                Toggle::make('enable')
                    ->label('Habilitado')
                    ->default(false)
                    ->required(),

                TextInput::make('twich_channel')
                    ->label('Canal de Twitch')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('youtube_channel')
                    ->label('Canal de YouTube')
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('other_channel')
                    ->label('Otro canal')
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }
}
