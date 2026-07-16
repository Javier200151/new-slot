<?php

namespace App\Filament\Resources\EventStatuses\Pages;

use App\Filament\Resources\EventStatuses\EventStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventStatuses extends ListRecords
{
    protected static string $resource = EventStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
