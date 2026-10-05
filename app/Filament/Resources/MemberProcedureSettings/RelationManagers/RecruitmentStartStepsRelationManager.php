<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;

class RecruitmentStartStepsRelationManager extends ProcedureStepDefinitionsRelationManager
{
    protected static string $procedureType = MemberProcedure::TYPE_RECRUITMENT_START;
    protected static ?string $title = 'Alta de recluta';
}
