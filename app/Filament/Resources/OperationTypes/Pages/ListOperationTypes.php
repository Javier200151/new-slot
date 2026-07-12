<?php

namespace App\Filament\Resources\OperationTypes\Pages;

use App\Filament\Resources\OperationTypes\OperationTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationTypes extends ListRecords
{
    protected static string $resource = OperationTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
