<?php

namespace App\Filament\Resources\EventStatuses\Tables;

use App\Models\EventStatus;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class EventStatusesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('is_system')
                    ->label('Sistema')
                    ->boolean()
                    ->trueIcon('heroicon-o-lock-closed')
                    ->falseIcon('heroicon-o-pencil-square')
                    ->tooltip(fn (EventStatus $record): string => $record->is_system
                        ? 'Estado protegido del sistema'
                        : 'Estado personalizado'),

                TextColumn::make('protection')
                    ->label('Protección')
                    ->state(fn (EventStatus $record): string => $record->is_system ? 'Protegido' : 'Personalizado')
                    ->badge()
                    ->color(fn (EventStatus $record): string => $record->is_system ? 'warning' : 'gray'),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('name')
            ->recordActions([
                EditAction::make()
                    ->disabled(fn (EventStatus $record): bool => $record->is_system)
                    ->tooltip(fn (EventStatus $record): ?string => $record->is_system
                        ? 'Los estados del sistema no pueden modificarse.'
                        : null),
            ]);
    }
}
