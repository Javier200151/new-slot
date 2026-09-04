<?php

namespace App\Filament\Resources\SqaGroups\Pages;

use App\Filament\Resources\SqaGroups\SqaGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
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
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
