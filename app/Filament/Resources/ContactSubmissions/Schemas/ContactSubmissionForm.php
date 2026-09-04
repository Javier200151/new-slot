<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use Filament\Forms\Components\DatePicker;
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
                TextInput::make('nickname')->label('Nick')->disabled(),
                TextInput::make('email')->label('Email')->disabled(),
                Toggle::make('is_recruitment')->label('Alistamiento')->disabled(),
                DateTimePicker::make('read_at')->label('Marcada como leída')->seconds(false),
                Textarea::make('message')->label('Mensaje')->rows(8)->disabled()->columnSpanFull(),
            ])->columns(2),

            Section::make('Datos personales de alistamiento')->schema([
                TextInput::make('full_name')->label('Nombre y apellidos reales')->disabled(),
                DatePicker::make('birth_date')->label('Fecha de nacimiento')->displayFormat('d/m/Y')->disabled(),
                TextInput::make('residence')->label('Lugar de residencia')->disabled(),
                TextInput::make('phone_whatsapp')->label('Teléfono / WhatsApp')->disabled(),
                Textarea::make('how_heard_us')->label('Cómo nos conociste')->rows(4)->disabled()->columnSpanFull(),
                Textarea::make('experience_summary')->label('Resumen de experiencia en simulación militar en Arma 3')->rows(6)->disabled()->columnSpanFull(),
            ])->columns(2),

            Section::make('Requisitos de alistamiento')->schema([
                Toggle::make('accepted_rules')->label('Normativa')->disabled(),
                Toggle::make('is_adult')->label('Mayor de edad')->disabled(),
                Toggle::make('accepts_contributions')->label('Aportaciones económicas')->disabled(),
                Toggle::make('has_required_game_content')->label('DLCs y Arma 3 original')->disabled(),
                Toggle::make('tuesday_available')->label('Disponible martes')->disabled(),
                Toggle::make('friday_available')->label('Disponible viernes')->disabled(),
                Toggle::make('has_previous_experience')->label('Experiencia previa')->disabled(),
            ])->columns(2),

            Section::make('Consentimientos')->schema([
                Toggle::make('accepted_privacy')->label('Política de privacidad')->disabled(),
                Toggle::make('accepted_contact')->label('Consentimiento de contacto')->disabled(),
            ])->columns(2),
        ]);
    }
}
