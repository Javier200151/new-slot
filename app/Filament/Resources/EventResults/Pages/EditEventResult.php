<?php

namespace App\Filament\Resources\EventResults\Pages;

use App\Filament\Resources\EventResults\EventResultResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditEventResult extends EditRecord
{
    protected static string $resource = EventResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
