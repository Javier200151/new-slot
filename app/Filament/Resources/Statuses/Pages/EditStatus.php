<?php

namespace App\Filament\Resources\Statuses\Pages;

use App\Filament\Resources\Statuses\StatusResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStatus extends EditRecord
{
    protected static string $resource = StatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar estado')
                ->disabled(fn (): bool => $this->record->deletionBlockReason() !== null)
                ->tooltip(fn (): ?string => $this->record->deletionBlockReason())
                ->requiresConfirmation(),

            RestoreAction::make(),
        ];
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if ($this->record->is_system) {
            unset($data['name']);
        }

        return $data;
    }
}
