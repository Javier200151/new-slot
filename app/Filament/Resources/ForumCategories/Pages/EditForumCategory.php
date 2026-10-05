<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use App\Models\ForumCategory;
use App\Services\ForumCategoryArchiveService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Str;

class EditForumCategory extends EditRecord
{
    protected static string $resource = ForumCategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->isDiary()) {
            // Diario mantiene intacta su identidad y comportamiento internos.
            // Desde Filament solo se personalizan nombre, singular, color, orden y estados.
            return [
                'title' => $data['title'] ?? $this->record->title,
                'singular' => $data['singular'] ?? $this->record->singular,
                'color' => $data['color'] ?? $this->record->color,
                'slug' => 'diario',
                'channel' => 'diary',
                'system_type' => ForumCategory::TYPE_DIARY,
                'is_system' => true,
                'allow_polls' => false,
                'process_type' => null,
                'icon' => $this->record->icon,
                'description' => $this->record->description,
                'hint' => $this->record->hint,
                'sort_order' => isset($data['sort_order']) ? (int) $data['sort_order'] : $this->record->sort_order,
                'is_enabled' => $this->record->is_enabled,
            ];
        }

        $data['slug'] = Str::slug((string) ($data['slug'] ?? $this->record->slug));
        $data['channel'] = 'personal';
        $data['system_type'] = ForumCategory::TYPE_STANDARD;
        $data['is_system'] = false;

        return $data;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('exportBackup')
                ->label('Descargar copia JSON')
                ->visible(fn (): bool => ! $this->record->isDiary() && ForumCategoryResource::canEdit($this->record))
                ->icon('heroicon-o-arrow-down-tray')
                ->color('gray')
                ->action(function (ForumCategoryArchiveService $archive) {
                    abort_unless(ForumCategoryResource::canEdit($this->record), 403);

                    $json = $archive->json($this->record);
                    $filename = 'foro-' . $this->record->slug . '-' . now()->format('Ymd-His') . '.json';

                    return response()->streamDownload(
                        static fn () => print($json),
                        $filename,
                        ['Content-Type' => 'application/json; charset=UTF-8'],
                    );
                }),

            Action::make('deleteCategory')
                ->label('Eliminar categoría')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->visible(fn (): bool => ForumCategoryResource::canDelete($this->record))
                ->requiresConfirmation()
                ->modalHeading('Eliminar categoría y todo su contenido')
                ->modalDescription("Esta acción es permanente. Se eliminarán todos los hilos, respuestas, votaciones, votos, procesos, reacciones y suscripciones. Descarga una copia JSON antes si quieres poder restaurarla. Si esta categoría se usa en una configuración automática, esa referencia quedará sin categoría.")
                ->form([
                    TextInput::make('confirmation')
                        ->label('Confirmación')
                        ->helperText('Escribe exactamente: ELIMINAR ' . $this->record->slug)
                        ->required(),
                ])
                ->action(function (array $data, ForumCategoryArchiveService $archive): void {
                    abort_unless(ForumCategoryResource::canDelete($this->record), 403);

                    if (($data['confirmation'] ?? '') !== 'ELIMINAR ' . $this->record->slug) {
                        Notification::make()
                            ->danger()
                            ->title('Confirmación incorrecta')
                            ->body('No se ha eliminado nada.')
                            ->send();
                        return;
                    }

                    $archive->deleteCategoryWithContent($this->record);

                    Notification::make()
                        ->success()
                        ->title('Categoría eliminada')
                        ->send();

                    $this->redirect(ForumCategoryResource::getUrl('index'));
                }),
        ];
    }
}
