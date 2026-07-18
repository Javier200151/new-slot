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
use Filament\Tables\Filters\SelectFilter;
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

                TextColumn::make('platform.name')
                    ->label('Plataforma')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable(),

                //ImageColumn::make('image')
                //    ->label('Imagen'),

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
                    ->badge()
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'day' => 'Día',
                        'night' => 'Noche',
                        'both' => 'Ambos',
                        'ambos' => 'Ambos',
                        default => 'Sin indicar',
                    })
                    ->color(fn (?string $state): string => match ($state) {
                        'day' => 'warning',
                        'night' => 'info',
                        'both', 'ambos' => 'gray',
                        default => 'gray',
                    }),

                TextColumn::make('orbat')
                    ->label('ORBAT')
                    ->state(function ($record): string {
                        $groups = $record->orbat['groups'] ?? [];
                        $slots = collect($groups)->sum(fn (array $group): int => count($group['slots'] ?? []));

                        return count($groups) . ' grupos / ' . $slots . ' slots';
                    })
                    ->badge()
                    ->color('gray'),

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
                SelectFilter::make('operation_type_id')
                    ->label('Tipo')
                    ->multiple()
                    ->relationship('operationType', 'name'),

                SelectFilter::make('operation_status_id')
                    ->label('Estado')
                    ->multiple()
                    ->relationship('operationStatus', 'name'),

                SelectFilter::make('platform_id')
                    ->label('Plataforma')
                    ->multiple()
                    ->relationship('platform', 'name'),

                SelectFilter::make('map_id')
                    ->label('Mapa')
                    ->multiple()
                    ->relationship('map', 'name'),

                SelectFilter::make('period_id')
                    ->label('Periodo')
                    ->multiple()
                    ->relationship('period', 'name'),

                SelectFilter::make('day_id')
                    ->label('Día')
                    ->multiple()
                    ->relationship('day', 'name'),

                SelectFilter::make('editor_id')
                    ->label('Editor')
                    ->multiple()
                    ->relationship('editor', 'nick'),

                //TrashedFilter::make(),
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
