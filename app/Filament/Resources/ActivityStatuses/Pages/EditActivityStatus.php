<?php

namespace App\Filament\Resources\ActivityStatuses\Pages;

use App\Filament\Resources\ActivityStatuses\ActivityStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityStatus extends EditRecord
{
    protected static string $resource = ActivityStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
