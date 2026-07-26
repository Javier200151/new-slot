<?php

namespace App\Filament\Resources\RadioModels\Pages;

use App\Filament\Resources\RadioModels\RadioModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRadioModels extends ListRecords
{
    protected static string $resource = RadioModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
