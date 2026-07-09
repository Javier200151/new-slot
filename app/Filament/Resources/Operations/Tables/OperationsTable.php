<?php

namespace App\Filament\Resources\Operations\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class OperationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('operationType.name')
                    ->label('Tipo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('operationStatus.name')
                    ->label('Estado')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('campaign.name')
                    ->label('Campaña')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Fecha')
                    ->dateTime()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                ImageColumn::make('image')
                    ->label('Imagen'),

                TextColumn::make('map.name')
                    ->label('Mapa')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('enemyFactions.name')
                    ->label('Facciones enemigas')
                    ->listWithLineBreaks()
                    ->bulleted()
                    ->searchable()
                    ->sortable(),

                TextColumn::make('period.name')
                    ->label('Periodo')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('day.name')
                    ->label('Día')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('editor.nick')
                    ->label('Editor')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('ocap')
                    ->label('OCAP')
                    ->boolean(),

                IconColumn::make('respawn')
                    ->label('Respawn')
                    ->boolean(),

                IconColumn::make('jip')
                    ->label('JIP')
                    ->boolean(),

                TextColumn::make('day_or_night')
                    ->label('Día/noche')
                    ->badge(),

                TextColumn::make('pbo')
                    ->label('PBO')
                    ->searchable(),

                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('createdBy.nick')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->searchable()
                    ->sortable(),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }
}
