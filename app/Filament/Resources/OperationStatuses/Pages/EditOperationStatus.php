<?php

namespace App\Filament\Resources\OperationStatuses\Pages;

use App\Filament\Resources\OperationStatuses\OperationStatusResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationStatus extends EditRecord
{
    protected static string $resource = OperationStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
