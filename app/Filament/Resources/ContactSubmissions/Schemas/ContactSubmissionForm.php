<?php

namespace App\Filament\Resources\ContactSubmissions\Schemas;

use App\Models\ContactSubmission;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

class ContactSubmissionForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            Section::make('Consulta')
                ->description('Mensaje de contacto general. No forma parte del flujo de alistamiento.')
                ->schema([
                    TextInput::make('nickname')
                        ->label('Nick')
                        ->disabled(),
                    TextInput::make('email')
                        ->label('Email')
                        ->disabled(),
                    Placeholder::make('received_at')
                        ->label('Recibida')
                        ->content(fn (?ContactSubmission $record): string => $record?->created_at?->format('d/m/Y H:i') ?? '—'),
                    DateTimePicker::make('read_at')
                        ->label('Marcada como leída')
                        ->seconds(false),
                    Textarea::make('message')
                        ->label('Mensaje')
                        ->rows(10)
                        ->disabled()
                        ->columnSpanFull(),
                ])
                ->columns(2)
                ->columnSpanFull(),
        ]);
    }
}
