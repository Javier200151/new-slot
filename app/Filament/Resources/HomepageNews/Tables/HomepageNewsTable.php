<?php

namespace App\Filament\Resources\HomepageNews\Tables;

use App\Models\HomepageNews;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class HomepageNewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')->label('Título')->searchable()->wrap(),
                TextColumn::make('category')->label('Categoría')->formatStateUsing(fn ($state) => HomepageNews::CATEGORIES[$state] ?? 'Comunicado'),
                TextColumn::make('source_type')->label('Origen')->formatStateUsing(fn ($state) => $state === 'automatic' ? 'Automático' : 'Manual'),
                IconColumn::make('is_published')->label('Visible en la web')->boolean(),
                TextColumn::make('published_at')->label('Publicación')->dateTime('d/m/Y H:i')->sortable(),
                TextColumn::make('sort_order')->label('Orden')->sortable(),
            ])
            ->defaultSort('published_at', 'desc')
            ->filters([
                TernaryFilter::make('is_published')
                    ->label('Visibilidad')
                    ->placeholder('Todos')
                    ->trueLabel('Visibles')
                    ->falseLabel('Ocultos'),
                TrashedFilter::make()->label('Eliminados'),
            ])
            ->recordActions([
                EditAction::make()->visible(fn (HomepageNews $record): bool => ! $record->trashed()),
                Action::make('hide')
                    ->label('Ocultar')
                    ->icon('heroicon-o-eye-slash')
                    ->color('warning')
                    ->authorize('update')
                    ->visible(fn (HomepageNews $record): bool => ! $record->trashed() && $record->is_published)
                    ->requiresConfirmation()
                    ->modalDescription('No se mostrará ni en la portada ni en el historial. Podrás volver a mostrarlo cuando quieras.')
                    ->action(fn (HomepageNews $record) => $record->update(['is_published' => false])),
                Action::make('show')
                    ->label('Mostrar')
                    ->icon('heroicon-o-eye')
                    ->color('success')
                    ->authorize('update')
                    ->visible(fn (HomepageNews $record): bool => ! $record->trashed() && ! $record->is_published)
                    ->action(fn (HomepageNews $record) => $record->update(['is_published' => true])),
                DeleteAction::make()
                    ->label('Eliminar')
                    ->visible(fn (HomepageNews $record): bool => ! $record->trashed())
                    ->modalDescription('El anuncio se retirará de la web y quedará en Eliminados, por si necesitas recuperarlo.'),
                RestoreAction::make()
                    ->label('Recuperar')
                    ->visible(fn (HomepageNews $record): bool => $record->trashed()),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hideSelected')
                        ->label('Ocultar seleccionados')
                        ->icon('heroicon-o-eye-slash')
                        ->color('warning')
                        ->authorize(fn (): bool => (bool) auth()->user()?->can('homepage-news.update'))
                        ->requiresConfirmation()
                        ->modalDescription('Los anuncios seleccionados desaparecerán de la portada y del historial, sin eliminarse.')
                        ->action(function (Collection $records): void {
                            abort_unless(auth()->user()?->can('homepage-news.update'), 403);
                            $records->each(fn (HomepageNews $record) => ! $record->trashed() ? $record->update(['is_published' => false]) : null);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('showSelected')
                        ->label('Mostrar seleccionados')
                        ->icon('heroicon-o-eye')
                        ->color('success')
                        ->authorize(fn (): bool => (bool) auth()->user()?->can('homepage-news.update'))
                        ->requiresConfirmation()
                        ->modalDescription('Los anuncios seleccionados volverán a aparecer si ya ha llegado su fecha de publicación.')
                        ->action(function (Collection $records): void {
                            abort_unless(auth()->user()?->can('homepage-news.update'), 403);
                            $records->each(fn (HomepageNews $record) => ! $record->trashed() ? $record->update(['is_published' => true]) : null);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make()
                        ->label('Eliminar seleccionados')
                        ->modalDescription('Los anuncios seleccionados quedarán retirados de la web. Podrás recuperarlos desde el filtro Eliminados.'),
                    RestoreBulkAction::make()->label('Recuperar seleccionados'),
                ]),
            ]);
    }
}
