<?php

namespace App\Filament\Resources\TreasurySettings\Pages;

use App\Filament\Resources\TreasurySettings\TreasurySettingResource;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Enums\Width;

class EditTreasurySetting extends EditRecord
{
    protected static string $resource = TreasurySettingResource::class;

    protected Width | string | null $maxContentWidth = Width::Full;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
