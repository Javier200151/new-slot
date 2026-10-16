<?php

namespace Tests\Unit;

use App\Filament\Resources\MemberProcedures\MemberProcedureResource;
use App\Models\MemberProcedure;
use App\Support\PermissionCatalog;
use Tests\TestCase;

class AllyNicknamePermissionContractTest extends TestCase
{
    public function test_procedure_pending_states_are_distinct_from_resolved_states(): void
    {
        $this->assertNotSame(MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_COMPLETED);
        $this->assertNotSame(MemberProcedure::STATUS_ERROR, MemberProcedure::STATUS_CANCELLED);
    }

    public function test_ally_nickname_permission_is_a_separate_role_permission(): void
    {
        $this->assertSame('Editar nick de aliados',
            config('newslot-permissions.groups.events.resources.orbat.actions.edit_ally_names'));
    }
}
