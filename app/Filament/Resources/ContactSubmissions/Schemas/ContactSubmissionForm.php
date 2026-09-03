<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Solicitud')->schema([
                TextInput::make('email')->label('Email')->disabled(),
                Toggle::make('is_recruitment')->label('Alistamiento')->disabled(),
                Textarea::make('message')->label('Mensaje')->rows(8)->disabled()->columnSpanFull(),
                DateTimePicker::make('read_at')->label('Marcada como leída')->seconds(false),
            ])->columns(2),
            Section::make('Datos de alistamiento')->schema([
                Toggle::make('accepted_rules')->label('Normativa')->disabled(),
                Toggle::make('is_adult')->label('Mayor de edad')->disabled(),
                Toggle::make('accepts_contributions')->label('Aportaciones')->disabled(),
                Toggle::make('has_required_game_content')->label('Arma 3 + DLC/CDLC')->disabled(),
                Toggle::make('tuesday_available')->label('Disponible martes')->disabled(),
                Toggle::make('friday_available')->label('Disponible viernes')->disabled(),
                Toggle::make('has_previous_experience')->label('Experiencia previa')->disabled(),
            ])->columns(2),
        ]);
    }
}
