<?php

namespace App\Filament\Resources\Armies\Pages;

use App\Filament\Resources\Armies\ArmyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditArmy extends EditRecord
{
    protected static string $resource = ArmyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
