<?php

namespace App\Filament\Resources\EventStatuses\Pages;

use App\Filament\Resources\EventStatuses\EventStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventStatus extends EditRecord
{
    protected static string $resource = EventStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->label('Eliminar estado')
                ->disabled(fn (): bool => $this->record->deletionBlockReason() !== null)
                ->tooltip(fn (): ?string => $this->record->deletionBlockReason())
                ->requiresConfirmation(),
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
