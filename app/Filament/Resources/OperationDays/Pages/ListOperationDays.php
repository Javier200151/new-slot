<?php

namespace App\Filament\Resources\OperationDays\Pages;

use App\Filament\Resources\OperationDays\OperationDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListOperationDays extends ListRecords
{
    protected static string $resource = OperationDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
