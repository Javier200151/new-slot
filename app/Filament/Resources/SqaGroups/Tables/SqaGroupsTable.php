<?php

namespace App\Filament\Resources\SqaGroups\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\ColorColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class SqaGroupsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('icon')
                    ->label('Icono')
                    ->disk('public')
                    ->imageWidth(36)
                    ->imageHeight(36)
                    ->extraImgAttributes([
                        'style' => 'object-fit: contain; max-width: 36px; max-height: 36px;',
                    ]),

                TextColumn::make('name')
                    ->label('Nombre')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('large_name')
                    ->label('Nombre largo')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('has_coordinator_role')
                    ->label('Figura coordinador')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('coordinator_display')
                    ->label('Coordinador')
                    ->state(fn ($record): string => ! $record->has_coordinator_role
                        ? 'No aplica'
                        : ($record->coordinatorAssignment?->user?->nick ?? 'Sin asignar')),

                ColorColumn::make('color')
                    ->label('Color'),

                IconColumn::make('show_in_organization')
                    ->label('Organigrama')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('display_order')
                    ->label('Orden')
                    ->numeric()
                    ->sortable(),

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
            ->defaultSort('display_order')
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
