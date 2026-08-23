<?php

namespace App\Filament\Resources\Streamers\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

class StreamersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Usuario')
                    ->searchable()
                    ->sortable(),

                IconColumn::make('enable')
                    ->label('Habilitado')
                    ->boolean()
                    ->sortable(),

                TextColumn::make('twitch_channel')
                    ->label('Twitch')
                    ->url(fn ($state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(45),

                TextColumn::make('youtube_channel')
                    ->label('YouTube')
                    ->url(fn ($state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(45),

                TextColumn::make('website_url')
                    ->label('Web')
                    ->url(fn ($state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(40),

                TextColumn::make('other_channel')
                    ->label('Otro canal')
                    ->url(fn ($state): ?string => $state)
                    ->openUrlInNewTab()
                    ->limit(40),

                TextColumn::make('created_at')
                    ->label('Creado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('updated_at')
                    ->label('Actualizado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),

                TextColumn::make('deleted_at')
                    ->label('Eliminado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable()
                    ->toggleable(
                        isToggledHiddenByDefault: true
                    ),
            ])
            ->filters([
                TernaryFilter::make('enable')
                    ->label('Habilitado'),

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