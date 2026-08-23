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
                    ->label('Habilitado como streamer')
                    ->helperText(
                        'Permite a este usuario aparecer en '
                        . 'la sección pública de directos.'
                    )
                    ->default(false)
                    ->required(),

                TextInput::make('twitch_channel')
                    ->label('Canal de Twitch')
                    ->placeholder(
                        'https://www.twitch.tv/usuario'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('youtube_channel')
                    ->label('Canal de YouTube')
                    ->placeholder(
                        'https://www.youtube.com/@usuario'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('website_url')
                    ->label('Página web')
                    ->placeholder(
                        'https://ejemplo.com'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('other_channel')
                    ->label('Otro canal')
                    ->url()
                    ->maxLength(255)
                    ->nullable(),
            ]);
    }
}