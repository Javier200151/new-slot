<?php

namespace App\Filament\Resources\ActivityDays\Pages;

use App\Filament\Resources\ActivityDays\ActivityDayResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityDay extends EditRecord
{
    protected static string $resource = ActivityDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
