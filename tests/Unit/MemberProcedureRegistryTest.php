<?php

namespace Tests\Unit;

use App\Models\MemberProcedure;
use App\Services\MemberProcedures\MemberProcedureRegistry;
use PHPUnit\Framework\TestCase;

class MemberProcedureRegistryTest extends TestCase
{
    public function test_registry_contains_all_member_lifecycle_procedures(): void
    {
        $definitions = (new MemberProcedureRegistry())->definitions();

        $this->assertSame([
            MemberProcedure::TYPE_RECRUITMENT_START,
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE,
            MemberProcedure::TYPE_REACTIVATION,
            MemberProcedure::TYPE_RESERVE,
            MemberProcedure::TYPE_DEPARTURE,
            MemberProcedure::TYPE_DISMISSAL,
        ], array_keys($definitions));
    }

    public function test_personal_data_purge_depends_on_google_sheet_transfer(): void
    {
        $steps = collect((new MemberProcedureRegistry())->definition(MemberProcedure::TYPE_RECRUITMENT_COMPLETE)['steps'])
            ->keyBy('key');

        $this->assertSame(
            ['google_sheets_transfer'],
            $steps['purge_recruitment_personal']['depends_on'],
        );
    }
}
