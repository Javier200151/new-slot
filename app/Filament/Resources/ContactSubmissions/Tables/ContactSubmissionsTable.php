<?php

namespace App\Filament\Resources\ContactSubmissions\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ContactSubmissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('nickname')
                    ->label('Nick')
                    ->searchable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable()
                    ->copyable(),
                TextColumn::make('message')
                    ->label('Mensaje')
                    ->limit(90)
                    ->wrap(),
                TextColumn::make('created_at')
                    ->label('Recibido')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                IconColumn::make('read_at')
                    ->label('Leída')
                    ->boolean()
                    ->state(fn ($record): bool => filled($record->read_at)),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->label('Abrir'),
            ]);
    }
}
