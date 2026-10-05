<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use App\Services\ForumCategoryArchiveService;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Forms\Components\FileUpload;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Throwable;

class ListForumCategories extends ListRecords
{
    protected static string $resource = ForumCategoryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
            Action::make('importBackup')
                ->label('Importar copia JSON')
                ->visible(fn (): bool => ForumCategoryResource::canCreate())
                ->icon('heroicon-o-arrow-up-tray')
                ->color('gray')
                ->form([
                    FileUpload::make('backup')
                        ->label('Copia de categoría')
                        ->disk('local')
                        ->directory('forum-category-imports')
                        ->acceptedFileTypes(['application/json', 'text/json', 'text/plain', 'application/octet-stream'])
                        ->helperText('La copia debe haberse generado desde Categorías del foro > JSON. No sobrescribe una categoría existente con el mismo identificador.')
                        ->required(),
                ])
                ->modalHeading('Importar categoría del foro')
                ->modalDescription('Se restaurarán la categoría, hilos, respuestas, votaciones, votos, procesos, reacciones y suscripciones incluidos en la copia.')
                ->action(function (array $data, ForumCategoryArchiveService $archive): void {
                    $stored = (string) $data['backup'];
                    $path = Storage::disk('local')->path($stored);

                    abort_unless(ForumCategoryResource::canCreate(), 403);

                    try {
                        $category = $archive->importFile($path);
                    } catch (Throwable $exception) {
                        report($exception);

                        $message = $exception instanceof ValidationException
                            ? collect($exception->errors())->flatten()->first()
                            : $exception->getMessage();

                        Notification::make()
                            ->danger()
                            ->title('No se pudo importar la categoría')
                            ->body((string) $message)
                            ->persistent()
                            ->send();
                        return;
                    } finally {
                        Storage::disk('local')->delete($stored);
                    }

                    Notification::make()
                        ->success()
                        ->title('Categoría restaurada')
                        ->body("Se ha importado '{$category->title}' con su contenido.")
                        ->send();
                }),
        ];
    }
}
