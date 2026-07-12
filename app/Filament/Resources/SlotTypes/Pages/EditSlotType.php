<?php

namespace App\Filament\Resources\SlotTypes\Pages;

use App\Filament\Resources\SlotTypes\SlotTypeResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSlotType extends EditRecord
{
    protected static string $resource = SlotTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
