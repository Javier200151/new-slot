<?php

namespace App\Filament\Resources\OperationStatuses\Pages;

use App\Filament\Resources\OperationStatuses\OperationStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationStatuses extends ListRecords
{
    protected static string $resource = OperationStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
