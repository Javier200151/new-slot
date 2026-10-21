<?php

namespace App\Filament\Resources\Streams\Tables;

use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\Action;
use App\Models\Stream;
use App\Services\HomepageVodService;
use Filament\Notifications\Notification;
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

                TextColumn::make('vod_home_status')
                    ->label('VOD en portada')
                    ->state(fn (Stream $record): string => ! $record->isVodForHome()
                        ? '—'
                        : ($record->visible_on_home ? 'Visible' : 'Oculto'))
                    ->badge()
                    ->color(fn (Stream $record): string => ! $record->isVodForHome()
                        ? 'gray' : ($record->visible_on_home ? 'success' : 'warning')),

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
                Action::make('toggleVodVisibility')
                    ->label(fn (Stream $record): string => $record->visible_on_home ? 'Esconder VOD' : 'Mostrar VOD')
                    ->icon(fn (Stream $record): string => $record->visible_on_home ? 'heroicon-o-eye-slash' : 'heroicon-o-eye')
                    ->color(fn (Stream $record): string => $record->visible_on_home ? 'warning' : 'success')
                    ->visible(fn (Stream $record): bool => $record->isVodForHome())
                    ->requiresConfirmation()
                    ->modalHeading(fn (Stream $record): string => $record->visible_on_home ? '¿Esconder este VOD?' : '¿Mostrar este VOD?')
                    ->modalDescription('Esta acción solo cambia si el vídeo aparece en la portada. No borra la emisión.')
                    ->action(function (Stream $record): void {
                        $record->update(['visible_on_home' => ! $record->visible_on_home]);
                        app(HomepageVodService::class)->invalidateCache();
                        Notification::make()->title($record->visible_on_home
                            ? 'El VOD vuelve a estar visible en la portada'
                            : 'El VOD está oculto en la portada')->success()->send();
                    }),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
