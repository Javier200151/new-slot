<?php

namespace App\Filament\Resources\PublicNavigationSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PublicNavigationSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('items')
                    ->label('Posiciones configuradas')
                    ->state(fn ($record): int => count($record->items ?? [])),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
