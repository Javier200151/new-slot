<?php

namespace App\Filament\Resources\Streams\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class StreamsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('event.name')
                    ->label('Evento')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('event.date')
                    ->label('Fecha del evento')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('streamer.user.nick')
                    ->label('Streamer')
                    ->searchable()
                    ->sortable(),

                TextColumn::make('stream_url')
                    ->label('URL de emisión')
                    ->url(fn ($state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(60)
                    ->searchable(),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('event_id')
                    ->label('Evento')
                    ->multiple()
                    ->relationship('event', 'name'),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
