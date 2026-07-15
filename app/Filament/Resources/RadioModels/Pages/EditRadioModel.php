<?php

namespace App\Filament\Resources\RadioModels\Pages;

use App\Filament\Resources\RadioModels\RadioModelResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditRadioModel extends EditRecord
{
    protected static string $resource = RadioModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
