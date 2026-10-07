<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;

class NotPromotedStepsRelationManager extends ProcedureStepDefinitionsRelationManager
{
    protected static string $procedureType = MemberProcedure::TYPE_NOT_PROMOTED;
    protected static ?string $title = 'No promocionado';
}
