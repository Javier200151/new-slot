<?php

namespace App\Filament\Resources\InfrastructureSettings\Pages;

use App\Filament\Resources\InfrastructureSettings\InfrastructureSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditInfrastructureSetting extends EditRecord
{
    protected static string $resource = InfrastructureSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
