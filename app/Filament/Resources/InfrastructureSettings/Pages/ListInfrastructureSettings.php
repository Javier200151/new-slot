<?php

namespace App\Filament\Resources\InfrastructureSettings\Pages;

use App\Filament\Resources\InfrastructureSettings\InfrastructureSettingResource;
use Filament\Resources\Pages\ListRecords;

class ListInfrastructureSettings extends ListRecords
{
    protected static string $resource = InfrastructureSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
