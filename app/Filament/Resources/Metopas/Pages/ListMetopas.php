<?php

namespace App\Filament\Resources\Metopas\Pages;

use App\Filament\Resources\Metopas\MetopaResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListMetopas extends ListRecords
{
    protected static string $resource = MetopaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
