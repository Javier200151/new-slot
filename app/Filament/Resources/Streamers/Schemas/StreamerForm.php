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
                        'Permite a este usuario aparecer en la sección pública de directos. '
                        . 'Si tiene Twitch o YouTube configurado, el sistema intentará detectar automáticamente cuándo está en directo '
                        . 'y también incluirá sus últimos VODs en portada.'
                    )
                    ->default(false)
                    ->required(),

                TextInput::make('twitch_channel')
                    ->label('Canal de Twitch')
                    ->placeholder('https://www.twitch.tv/usuario')
                    ->helperText(
                        'Con este enlace es suficiente. El sistema detecta automáticamente si el canal está en directo y consulta sus VODs. '
                        . 'Twitch exige las credenciales globales TWITCH_CLIENT_ID y TWITCH_CLIENT_SECRET.'
                    )
                    ->url()
                    ->maxLength(255)
                    ->nullable(),

                Toggle::make('auto_import_clips')
                    ->label('Importar clips de Twitch')
                    ->default(false),

                Toggle::make('auto_import_youtube_clips')
                    ->label('Importar clips de YouTube')
                    ->helperText('Importa vídeos cortos públicos de hasta 3 minutos del canal.')
                    ->default(false),

                Select::make('clip_import_window')
                    ->label('Buscar clips de')
                    ->options([
                        '7d' => 'Últimos 7 días',
                        '1m' => 'Último mes',
                        '6m' => 'Últimos 6 meses',
                        '1y' => 'Último año',
                    ])
                    ->default('7d')
                    ->required(),

                Select::make('clip_import_mode')
                    ->label('Clips permitidos')
                    ->helperText('En YouTube, Arma se identifica por el título o la descripción.')
                    ->options(['all' => 'Todos los clips', 'arma' => 'Solo Arma 3 y Arma Reforger'])
                    ->default('all')
                    ->required(),

                TextInput::make('youtube_channel')
                    ->label('Canal de YouTube')
                    ->placeholder('https://www.youtube.com/@usuario')
                    ->helperText(
                        'Con el enlace del canal es suficiente. El sistema resuelve automáticamente el Channel ID, detecta si está en directo '
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
