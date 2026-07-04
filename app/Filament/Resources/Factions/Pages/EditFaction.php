<?php

namespace App\Filament\Resources\Factions\Pages;

use App\Filament\Resources\Factions\FactionResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditFaction extends EditRecord
{
    protected static string $resource = FactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
