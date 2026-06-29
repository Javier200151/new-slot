<?php

namespace App\Filament\Resources\Activities\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;

class ActivitiesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('Fecha y hora')
                    ->dateTime('d/m/Y H:i:s')
                    ->sortable(),

                TextColumn::make('causer.nick')
                    ->label('Quién hizo el cambio')
                    ->default('Sistema')
                    ->searchable(),

                TextColumn::make('event')
                    ->label('Tipo de cambio')
                    ->badge()
                    ->sortable(),

                TextColumn::make('subject_type')
                    ->label('Elemento modificado')
                    ->formatStateUsing(fn ($state) => class_basename($state))
                    ->sortable(),

                TextColumn::make('subject_id')
                    ->label('ID afectado')
                    ->sortable(),

                TextColumn::make('ip_address')
                    ->label('IP'),

                TextColumn::make('url')
                    ->label('Ruta')
                    ->limit(35),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()
                    ->label('Ver detalle'),
            ]);
    }
}
