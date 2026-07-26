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
            DeleteAction::make(),
        ];
    }
}
