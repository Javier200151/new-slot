<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;

class RecruitmentCompleteStepsRelationManager extends ProcedureStepDefinitionsRelationManager
{
    protected static string $procedureType = MemberProcedure::TYPE_RECRUITMENT_COMPLETE;
    protected static ?string $title = 'Alta de calavera';
}
