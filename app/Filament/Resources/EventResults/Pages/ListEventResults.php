<?php

namespace App\Filament\Resources\EventResults\Pages;

use App\Filament\Resources\EventResults\EventResultResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventResults extends ListRecords
{
    protected static string $resource = EventResultResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
