<?php

namespace App\Filament\Resources\ActivityDays\Pages;

use App\Filament\Resources\ActivityDays\ActivityDayResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityDays extends ListRecords
{
    protected static string $resource = ActivityDayResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
