<?php

namespace App\Filament\Resources\EventComments\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class EventCommentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('user.nick')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('parent.comment')
                    ->label('Respuesta a')
                    ->limit(40)
                    ->toggleable(),

                TextColumn::make('comment')
                    ->label('Comentario')
                    ->limit(80)
                    ->searchable(),

                IconColumn::make('is_pinned')
                    ->label('Fijado')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('updatedBy.nick')
                    ->label('Actualizado por')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('created_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('updated_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('deleted_at')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Evento')
                    ->multiple()
                    ->relationship('event', 'name'),

                SelectFilter::make('user_id')
                    ->label('Usuario')
                    ->multiple()
                    ->relationship('user', 'nick'),

                TrashedFilter::make(),
            ])
            ->defaultSort('created_at', 'desc')
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
