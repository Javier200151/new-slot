<?php

namespace App\Filament\Resources\ForumCategories\Tables;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use App\Models\ForumCategory;
use App\Services\ForumCategoryArchiveService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ForumCategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('sort_order')
                    ->label('Orden')
                    ->sortable(),

                TextColumn::make('icon')
                    ->label('')
                    ->alignCenter(),

                TextColumn::make('title')
                    ->label('Categoría')
                    ->searchable()
                    ->sortable()
                    ->description(fn (ForumCategory $record): string => $record->isDiary() ? 'Categoría interna · Diario' : '/area/foro/' . $record->slug),

                TextColumn::make('statuses.name')
                    ->label('Estados que la ven')
                    ->badge()
                    ->separator(', '),

                IconColumn::make('allow_polls')
                    ->label('Votaciones')
                    ->boolean(),

                TextColumn::make('process_type')
                    ->label('Flujo')
                    ->badge()
                    ->placeholder('Normal')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'convocatoria' => 'Convocatoria',
                        'propuestas' => 'Propuesta',
                        'consulta' => 'Consulta',
                        default => 'Normal',
                    }),

                IconColumn::make('is_enabled')
                    ->label('Activa')
                    ->boolean(),
            ])
            ->defaultSort('sort_order')
            ->recordActions([
                EditAction::make()
                    ->visible(fn (ForumCategory $record): bool => ForumCategoryResource::canEdit($record)),

                Action::make('exportBackup')
                    ->label('JSON')
                    ->icon('heroicon-o-arrow-down-tray')
                    ->color('gray')
                    ->visible(fn (ForumCategory $record): bool => ! $record->isDiary() && ForumCategoryResource::canEdit($record))
                    ->action(function (ForumCategory $record, ForumCategoryArchiveService $archive) {
                        abort_unless(ForumCategoryResource::canEdit($record), 403);
                        $json = $archive->json($record);
                        $filename = 'foro-' . $record->slug . '-' . now()->format('Ymd-His') . '.json';

                        return response()->streamDownload(
                            static fn () => print($json),
                            $filename,
                            ['Content-Type' => 'application/json; charset=UTF-8'],
                        );
                    }),

                Action::make('deleteCategory')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->visible(fn (ForumCategory $record): bool => ForumCategoryResource::canDelete($record))
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar categoría y todo su contenido')
                    ->modalDescription(fn (ForumCategory $record): string => "Esta acción elimina permanentemente '{$record->title}', sus hilos, respuestas, votaciones, votos, reacciones, postulaciones y suscripciones. Descarga antes el JSON si quieres conservar una copia. Si esta categoría se usa en una configuración automática, esa referencia quedará sin categoría.")
                    ->form(fn (ForumCategory $record): array => [
                        TextInput::make('confirmation')
                            ->label('Confirmación')
                            ->helperText("Escribe exactamente: ELIMINAR {$record->slug}")
                            ->required(),
                    ])
                    ->action(function (ForumCategory $record, array $data, ForumCategoryArchiveService $archive): void {
                        abort_unless(ForumCategoryResource::canDelete($record), 403);

                        $expected = 'ELIMINAR ' . $record->slug;
                        if (($data['confirmation'] ?? '') !== $expected) {
                            Notification::make()
                                ->danger()
                                ->title('Confirmación incorrecta')
                                ->body('No se ha eliminado nada.')
                                ->send();
                            return;
                        }

                        $archive->deleteCategoryWithContent($record);

                        Notification::make()
                            ->success()
                            ->title('Categoría eliminada')
                            ->body('La categoría y todo su contenido asociado se han eliminado.')
                            ->send();
                    }),
            ]);
    }
}
