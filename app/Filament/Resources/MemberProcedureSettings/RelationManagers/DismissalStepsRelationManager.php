<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;

class DismissalStepsRelationManager extends ProcedureStepDefinitionsRelationManager
{
    protected static string $procedureType = MemberProcedure::TYPE_DISMISSAL;
    protected static ?string $title = 'Cese';
}
