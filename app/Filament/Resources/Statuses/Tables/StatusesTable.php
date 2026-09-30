<?php

namespace App\Filament\Resources\Statuses\Tables;

use Filament\Actions\EditAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                ColorColumn::make('color')
                    ->label('Color'),

                IconColumn::make('is_system')
                    ->label('Sistema')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil-square')
                    ->tooltip(fn ($record): string => $record->is_system
                        ? 'Estado protegido del sistema'
                        : 'Estado creado desde Filament'
                    ),

                TextColumn::make('protection')
                    ->label('Protección')
                    ->state(fn ($record): string => $record->is_system ? 'Protegido' : 'Editable')
                    ->badge()
                    ->color(fn ($record): string => $record->is_system ? 'warning' : 'success'),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([]);
    }
}
