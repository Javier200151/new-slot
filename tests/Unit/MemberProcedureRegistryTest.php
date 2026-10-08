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
            MemberProcedure::TYPE_NOT_PROMOTED,
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

    public function test_not_promoted_is_a_recruit_exit_to_not_promoted_without_member_integrations(): void
    {
        $steps = collect((new MemberProcedureRegistry())->definition(MemberProcedure::TYPE_NOT_PROMOTED)['steps'])
            ->keyBy('key');

        $this->assertArrayHasKey('not_promoted_validation', $steps);
        $this->assertArrayHasKey('status_not_promoted', $steps);
        $this->assertArrayNotHasKey('armasquads_delete', $steps);
        $this->assertArrayNotHasKey('google_sheets_status_sync', $steps);
        $this->assertSame(['not_promoted_validation'], $steps['status_not_promoted']['depends_on']);
        $this->assertStringContainsString('NO PROMOCIONADO', $steps['status_not_promoted']['label']);
        $this->assertStringNotContainsString('BAJA', $steps['status_not_promoted']['label']);
    }

    public function test_discord_steps_are_automatic_in_all_lifecycle_procedures(): void
    {
        $registry = new MemberProcedureRegistry();

        $expected = [
            MemberProcedure::TYPE_RECRUITMENT_START => 'discord_recruit',
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => 'discord_alpha',
            MemberProcedure::TYPE_NOT_PROMOTED => 'discord_not_promoted',
            MemberProcedure::TYPE_REACTIVATION => 'discord_reactivation',
            MemberProcedure::TYPE_RESERVE => 'discord_reserve',
            MemberProcedure::TYPE_DEPARTURE => 'discord_departure',
            MemberProcedure::TYPE_DISMISSAL => 'discord_departure',
        ];

        foreach ($expected as $type => $stepKey) {
            $steps = collect($registry->definition($type)['steps'])->keyBy('key');
            $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $steps[$stepKey]['kind'], $type . ' debe automatizar Discord.');
        }
    }
    public function test_telegram_announces_recruit_updates_keeps_member_emails_and_manual_removals(): void
    {
        $registry = new MemberProcedureRegistry();

        $recruitment = collect($registry->definition(MemberProcedure::TYPE_RECRUITMENT_START)['steps'])->keyBy('key');
        $completion = collect($registry->definition(MemberProcedure::TYPE_RECRUITMENT_COMPLETE)['steps'])->keyBy('key');
        $reactivation = collect($registry->definition(MemberProcedure::TYPE_REACTIVATION)['steps'])->keyBy('key');
        $reserve = collect($registry->definition(MemberProcedure::TYPE_RESERVE)['steps'])->keyBy('key');
        $notPromoted = collect($registry->definition(MemberProcedure::TYPE_NOT_PROMOTED)['steps'])->keyBy('key');
        $departure = collect($registry->definition(MemberProcedure::TYPE_DEPARTURE)['steps'])->keyBy('key');

        $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $recruitment['telegram_recruit_update']['kind']);
        $this->assertSame(['status_recruit'], $recruitment['telegram_recruit_update']['depends_on']);
        $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $notPromoted['telegram_recruit_update']['kind']);
        $this->assertSame(['status_not_promoted'], $notPromoted['telegram_recruit_update']['depends_on']);
        $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $completion['telegram_groups_email']['kind']);
        $this->assertSame(['status_active'], $completion['telegram_groups_email']['depends_on']);
        $this->assertSame(MemberProcedureStep::KIND_AUTOMATIC, $reactivation['telegram_groups_email']['kind']);
        $this->assertSame(['status_active'], $reactivation['telegram_groups_email']['depends_on']);
        $this->assertSame(MemberProcedureStep::KIND_MANUAL, $reserve['telegram_leave_official']['kind']);
        $this->assertStringContainsString('ALPHA Oficial', $reserve['telegram_leave_official']['label']);
        $this->assertSame(MemberProcedureStep::KIND_MANUAL, $departure['telegram_leave_all']['kind']);
    }

    public function test_teamspeak_steps_remain_manual_across_the_member_lifecycle(): void
    {
        $registry = new MemberProcedureRegistry();

        $expected = [
            MemberProcedure::TYPE_RECRUITMENT_START => 'ts3_recruit',
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => 'ts3_alpha',
            MemberProcedure::TYPE_NOT_PROMOTED => 'ts3_not_promoted',
            MemberProcedure::TYPE_REACTIVATION => 'ts3_reactivation',
            MemberProcedure::TYPE_RESERVE => 'ts3_reserve',
            MemberProcedure::TYPE_DEPARTURE => 'ts3_departure',
            MemberProcedure::TYPE_DISMISSAL => 'ts3_departure',
        ];

        foreach ($expected as $type => $stepKey) {
            $steps = collect($registry->definition($type)['steps'])->keyBy('key');
            $this->assertArrayHasKey($stepKey, $steps, $type . ' debe incluir su tarea manual de TeamSpeak 3.');
            $this->assertSame(MemberProcedureStep::KIND_MANUAL, $steps[$stepKey]['kind'], $type . ' no debe automatizar TeamSpeak 3.');
        }
    }

}
