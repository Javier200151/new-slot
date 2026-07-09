<?php

namespace App\Filament\Resources\OperationDays\Pages;

use App\Filament\Resources\OperationDays\OperationDayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditOperationDay extends EditRecord
{
    protected static string $resource = OperationDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
