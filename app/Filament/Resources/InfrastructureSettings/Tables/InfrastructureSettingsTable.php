<?php

namespace App\Filament\Resources\InfrastructureSettings\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class InfrastructureSettingsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('arma3_academy_host')
                    ->label('A3 Academia')
                    ->placeholder('Sin configurar'),
                TextColumn::make('arma3_operations_host')
                    ->label('A3 Operativos')
                    ->placeholder('Sin configurar'),
                TextColumn::make('reforger_academy_host')
                    ->label('Reforger Academia')
                    ->placeholder('Sin configurar'),
                TextColumn::make('reforger_operations_host')
                    ->label('Reforger Operativos')
                    ->placeholder('Sin configurar'),
                IconColumn::make('ts3_enabled')
                    ->label('TS3')
                    ->boolean(),
                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->since(),
            ])
            ->recordActions([
                EditAction::make(),
            ]);
    }
}
