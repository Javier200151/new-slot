<?php

namespace App\Filament\Resources\ActivityStatuses\Pages;

use App\Filament\Resources\ActivityStatuses\ActivityStatusResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityStatuses extends ListRecords
{
    protected static string $resource = ActivityStatusResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
