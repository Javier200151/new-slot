<?php

namespace App\Filament\Resources\TreasurySettings\Pages;

use App\Filament\Resources\TreasurySettings\TreasurySettingResource;
use App\Models\MemberProcedureSetting;
use Filament\Resources\Pages\ListRecords;

class ListTreasurySettings extends ListRecords
{
    protected static string $resource = TreasurySettingResource::class;

    public function mount(): void
    {
        MemberProcedureSetting::current();
        parent::mount();
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
