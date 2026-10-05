<?php

namespace App\Filament\Resources\MemberProcedureSettings\Pages;

use App\Filament\Resources\MemberProcedureSettings\MemberProcedureSettingResource;
use App\Models\MemberProcedureSetting;
use Filament\Resources\Pages\ListRecords;

class ListMemberProcedureSettings extends ListRecords
{
    protected static string $resource = MemberProcedureSettingResource::class;

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
