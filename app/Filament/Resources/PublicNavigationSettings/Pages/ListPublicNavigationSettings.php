<?php

namespace App\Filament\Resources\PublicNavigationSettings\Pages;

use App\Filament\Resources\PublicNavigationSettings\PublicNavigationSettingResource;
use App\Models\PublicNavigationSetting;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPublicNavigationSettings extends ListRecords
{
    protected static string $resource = PublicNavigationSettingResource::class;

    protected function getHeaderActions(): array
    {
        return PublicNavigationSetting::query()->exists()
            ? []
            : [CreateAction::make()];
    }
}
