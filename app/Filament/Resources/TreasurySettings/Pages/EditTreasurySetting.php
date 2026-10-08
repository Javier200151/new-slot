<?php

namespace App\Filament\Resources\TreasurySettings\Pages;

use App\Filament\Resources\TreasurySettings\TreasurySettingResource;
use Filament\Resources\Pages\EditRecord;

class EditTreasurySetting extends EditRecord
{
    protected static string $resource = TreasurySettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
