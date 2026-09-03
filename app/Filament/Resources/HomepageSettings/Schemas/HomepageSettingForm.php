<?php

namespace App\Filament\Resources\HomepageSettings\Schemas;

use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class HomepageSettingForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Contacto y alistamiento')
                ->description(
                    'Controla si el formulario público admite solicitudes de alistamiento. '
                    . 'Todas las consultas y solicitudes se envían a contactosquadalpha@gmail.com '
                    . 'usando el mismo SMTP configurado para los correos de NewSlot.'
                )
                ->schema([
                    Toggle::make('recruitment_open')
                        ->label('Alistamiento abierto')
                        ->helperText('Si está desactivado, la portada muestra únicamente el formulario de consultas.'),
                    TextInput::make('instagram_url')
                        ->label('Instagram de Squad ALPHA')
                        ->url()
                        ->helperText('Cuenta actual: @squadalpha_es. El feed de las 3 últimas publicaciones usa INSTAGRAM_ACCESS_TOKEN en el .env.')
                        ->maxLength(255),
                ])
                ->columns(2),
            Section::make('Bloque de actualidad')->schema([
                TextInput::make('news_title')->label('Título')->required()->maxLength(255),
                Textarea::make('news_intro')->label('Introducción')->rows(3)->columnSpanFull(),
            ]),
            Section::make('Bloque de VODs')->schema([
                TextInput::make('streams_title')->label('Título')->required()->maxLength(255),
                Textarea::make('streams_intro')->label('Introducción')->rows(3)->columnSpanFull(),
            ]),
        ]);
    }
}
