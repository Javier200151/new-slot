<?php

namespace App\Filament\Resources\VeterancySettings\Pages;

use App\Filament\Resources\VeterancySettings\VeterancySettingResource;
use App\Models\VeterancySetting;
use Filament\Resources\Pages\ListRecords;

class ListVeterancySettings extends ListRecords
{
    protected static string $resource = VeterancySettingResource::class;

    public function mount(): void
    {
        VeterancySetting::current();
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
