<?php

namespace App\Filament\Resources\SlotTypes\Pages;

use App\Filament\Resources\SlotTypes\SlotTypeResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSlotTypes extends ListRecords
{
    protected static string $resource = SlotTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
