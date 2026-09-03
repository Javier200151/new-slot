<?php

namespace App\Filament\Resources\Streamers\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;
use Illuminate\Validation\Rules\Unique;

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
                ->unique(
                    ignoreRecord: true,
                    modifyRuleUsing: fn (Unique $rule): Unique =>
                        $rule->whereNull('deleted_at')
                ),

                Toggle::make('enable')
                    ->label('Habilitado como streamer')
                    ->helperText(
                        'Permite a este usuario aparecer en '
                        . 'la sección pública de directos y en los últimos VODs de portada.'
                    )
                    ->default(false)
                    ->required(),

                TextInput::make('twitch_channel')
                    ->label('Canal de Twitch')
                    ->placeholder('https://www.twitch.tv/usuario')
                    ->helperText(
                        'Con este enlace es suficiente por streamer. NewSlot obtiene el usuario automáticamente. '
                        . 'Para consultar sus VODs, Twitch exige las credenciales globales TWITCH_CLIENT_ID y TWITCH_CLIENT_SECRET.'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('youtube_channel')
                    ->label('Canal de YouTube')
                    ->placeholder('https://www.youtube.com/@usuario')
                    ->helperText(
                        'Con el enlace del canal es suficiente. NewSlot resuelve automáticamente el Channel ID '
                        . 'y consulta los últimos vídeos mediante el feed público de YouTube, sin API Key.'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                TextInput::make('website_url')
                    ->label('Página web')
                    ->placeholder('https://ejemplo.com')
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
