<?php

namespace App\Filament\Resources\MemberProcedures\Pages;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use Filament\Resources\Pages\ListRecords;

class ListMemberProcedures extends ListRecords
{
    protected static string $resource = MemberProcedureResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
