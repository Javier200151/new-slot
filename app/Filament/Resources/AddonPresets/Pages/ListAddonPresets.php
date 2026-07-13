<?php

namespace App\Filament\Resources\AddonPresets\Pages;

use App\Filament\Resources\AddonPresets\AddonPresetResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAddonPresets extends ListRecords
{
    protected static string $resource = AddonPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
