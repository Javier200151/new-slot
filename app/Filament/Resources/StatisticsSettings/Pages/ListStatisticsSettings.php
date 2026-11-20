<?php

namespace App\Filament\Resources\StatisticsSettings\Pages;

use App\Filament\Resources\StatisticsSettings\StatisticsSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListStatisticsSettings extends ListRecords
{
    protected static string $resource = StatisticsSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
