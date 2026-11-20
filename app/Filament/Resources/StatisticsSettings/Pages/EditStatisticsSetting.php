<?php

namespace App\Filament\Resources\StatisticsSettings\Pages;

use App\Filament\Resources\StatisticsSettings\StatisticsSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditStatisticsSetting extends EditRecord
{
    protected static string $resource = StatisticsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
