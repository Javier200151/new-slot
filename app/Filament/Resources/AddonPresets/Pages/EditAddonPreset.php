<?php

namespace App\Filament\Resources\AddonPresets\Pages;

use App\Filament\Resources\AddonPresets\AddonPresetResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAddonPreset extends EditRecord
{
    protected static string $resource = AddonPresetResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
