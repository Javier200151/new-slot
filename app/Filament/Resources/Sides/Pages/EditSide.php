<?php

namespace App\Filament\Resources\Sides\Pages;

use App\Filament\Resources\Sides\SideResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditSide extends EditRecord
{
    protected static string $resource = SideResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
