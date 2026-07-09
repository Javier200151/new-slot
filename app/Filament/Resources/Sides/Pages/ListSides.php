<?php

namespace App\Filament\Resources\Sides\Pages;

use App\Filament\Resources\Sides\SideResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSides extends ListRecords
{
    protected static string $resource = SideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
