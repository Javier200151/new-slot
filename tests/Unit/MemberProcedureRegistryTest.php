<?php

namespace Tests\Unit;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
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

    public function test_personal_data_purge_depends_on_verified_google_sheet_transfer(): void
    {
        $steps = collect((new MemberProcedureRegistry())->definition(MemberProcedure::TYPE_RECRUITMENT_COMPLETE)['steps'])
            ->keyBy('key');

        $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $steps['google_sheets_transfer']['kind']);
        $this->assertSame(
            ['google_sheets_transfer'],
            $steps['purge_recruitment_personal']['depends_on'],
        );
    }

    public function test_status_sync_runs_after_the_status_transition_that_it_exports(): void
    {
        $registry = new MemberProcedureRegistry();

        $reactivation = collect($registry->definition(MemberProcedure::TYPE_REACTIVATION)['steps'])->keyBy('key');
        $reserve = collect($registry->definition(MemberProcedure::TYPE_RESERVE)['steps'])->keyBy('key');
        $departure = collect($registry->definition(MemberProcedure::TYPE_DEPARTURE)['steps'])->keyBy('key');
        $dismissal = collect($registry->definition(MemberProcedure::TYPE_DISMISSAL)['steps'])->keyBy('key');

        $this->assertSame(['status_active'], $reactivation['google_sheets_status_sync']['depends_on']);
        $this->assertSame(['status_reserve'], $reserve['google_sheets_status_sync']['depends_on']);
        $this->assertSame(['status_departed'], $departure['google_sheets_status_sync']['depends_on']);
        $this->assertSame(['status_dismissed'], $dismissal['google_sheets_status_sync']['depends_on']);
    }
}
