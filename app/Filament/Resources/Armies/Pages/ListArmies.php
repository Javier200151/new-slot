<?php

namespace App\Filament\Resources\Armies\Pages;

use App\Filament\Resources\Armies\ArmyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListArmies extends ListRecords
{
    protected static string $resource = ArmyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
