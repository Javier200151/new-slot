<?php

namespace App\Filament\Resources\MemberProcedureSettings\Pages;

use App\Filament\Resources\MemberProcedureSettings\MemberProcedureSettingResource;
use Filament\Resources\Pages\EditRecord;

class EditMemberProcedureSetting extends EditRecord
{
    protected static string $resource = MemberProcedureSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
