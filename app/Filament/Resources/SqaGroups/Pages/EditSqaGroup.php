<?php

namespace App\Filament\Resources\SqaGroups\Pages;

use App\Filament\Resources\SqaGroups\SqaGroupResource;
use App\Services\SqaGroupDiscordSyncService;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Support\Facades\Storage;

class EditSqaGroup extends EditRecord
{
    protected static string $resource = SqaGroupResource::class;

    protected ?string $iconToDeleteAfterSave = null;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $removeIcon = (bool) ($data['remove_icon'] ?? false);
        unset($data['remove_icon']);

        $currentIcon = filled($this->record->icon)
            ? (string) $this->record->icon
            : null;

        if ($removeIcon) {
            $data['icon'] = null;
        }

        $newIcon = filled($data['icon'] ?? null)
            ? (string) $data['icon']
            : null;

        /*
         * El archivo anterior solo se elimina DESPUÉS de que el registro se
         * haya guardado correctamente. Así, cancelar el formulario nunca deja
         * la BD apuntando a un fichero que ya no existe.
         */
        if ($currentIcon && $currentIcon !== $newIcon) {
            $this->iconToDeleteAfterSave = $currentIcon;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        if (! $this->iconToDeleteAfterSave) {
            return;
        }

        Storage::disk('public')->delete($this->iconToDeleteAfterSave);
        $this->iconToDeleteAfterSave = null;
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('syncDiscord')
                ->label('Sincronizar Discord')
                ->icon('heroicon-o-arrow-path')
                ->visible(fn (): bool => filled($this->record->discord_role_id) && ! $this->record->trashed())
                ->requiresConfirmation()
                ->modalDescription('Comprueba los miembros actuales de este Grupo SQA y les asigna el rol de Discord configurado. Úsalo también para reintentar una sincronización tras una incidencia de Discord.')
                ->action(function (): void {
                    $result = app(SqaGroupDiscordSyncService::class)->syncGroupNow($this->record);

                    if ($result['errors'] !== []) {
                        Notification::make()
                            ->warning()
                            ->title('Sincronización de Discord completada con incidencias')
                            ->body('Sincronizados: ' . $result['synced'] . ' · Omitidos sin Discord ID: ' . $result['skipped'] . ' · Errores: ' . count($result['errors']) . '. Revisa el log para el detalle.')
                            ->send();

                        foreach ($result['errors'] as $error) {
                            logger()->warning('Sincronización manual Grupo SQA / Discord', [
                                'sqa_group_id' => $this->record->getKey(),
                                'error' => $error,
                            ]);
                        }

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title('Grupo sincronizado con Discord')
                        ->body('Sincronizados: ' . $result['synced'] . ' · Omitidos sin Discord ID: ' . $result['skipped'] . '.')
                        ->send();
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
