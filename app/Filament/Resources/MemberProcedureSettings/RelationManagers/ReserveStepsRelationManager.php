<?php

namespace App\Filament\Resources\MemberProcedureSettings\RelationManagers;

use App\Models\MemberProcedure;

class ReserveStepsRelationManager extends ProcedureStepDefinitionsRelationManager
{
    protected static string $procedureType = MemberProcedure::TYPE_RESERVE;
    protected static ?string $title = 'Reserva';
}
