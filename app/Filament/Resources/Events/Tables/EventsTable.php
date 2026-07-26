<?php

namespace App\Filament\Resources\Events\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EventsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('operation.name')
                    ->label('Operativo')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('eventStatus.name')
                    ->label('Estado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'ACTIVO' => 'success',
                        'CANCELADO' => 'warning',
                        'FINALIZADO' => 'info',
                        'BORRADOR' => 'gray',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable(),

                TextColumn::make('date')
                    ->label('Fecha')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('end_date')
                    ->label('Fecha fin')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('eventResult.name')
                    ->label('Resultado')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'EXITO' => 'success',
                        'FRACASO' => 'warning',
                        'EXITO PARCIAL' => 'info',
                        default => 'gray',
                    })
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('duration')
                    ->label('Duración')
                    ->suffix(' min')
                    ->sortable(),

                TextColumn::make('orbat')
                    ->label('ORBAT')
                    ->state(function ($record): string {
                        $groups = $record->orbat['groups'] ?? [];
                        $slots = collect($groups)->sum(fn (array $group): int => count($group['slots'] ?? []));

                        return count($groups) . ' grupos / ' . $slots . ' slots';
                    })
                    ->badge()
                    ->color('gray'),

                TextColumn::make('ocap_url')
                    ->label('OCAP')
                    ->url(fn ($record): ?string => $record->ocap_url)
                    ->openUrlInNewTab()
                    ->toggleable(),

                TextColumn::make('createdBy.nick')
                    ->label('Creado por')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->searchable()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

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
            ])
            ->filters([
                SelectFilter::make('operation_id')
                    ->label('Operativo')
                    ->multiple()
                    ->relationship('operation', 'name'),

                SelectFilter::make('event_status_id')
                    ->label('Estado')
                    ->multiple()
                    ->relationship('eventStatus', 'name'),

                SelectFilter::make('event_result_id')
                    ->label('Resultado')
                    ->multiple()
                    ->relationship('eventResult', 'name'),

                TrashedFilter::make(),
            ])
            ->defaultSort('date', 'desc')
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
