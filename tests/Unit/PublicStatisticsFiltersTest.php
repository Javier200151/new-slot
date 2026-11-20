<?php

namespace Tests\Unit;

use App\Services\PublicStatisticsService;
use PHPUnit\Framework\TestCase;

class PublicStatisticsFiltersTest extends TestCase
{
    public function test_default_query_uses_participations_per_member_during_last_year(): void
    {
        $filters = (new PublicStatisticsService())->normalized([]);

        $this->assertSame('participations', $filters['metric']);
        $this->assertSame('user', $filters['group']);
        $this->assertSame('12m', $filters['period']);
        $this->assertSame(10, $filters['top']);
    }

    public function test_invalid_group_is_not_allowed_for_event_metrics(): void
    {
        $filters = (new PublicStatisticsService())->normalized([
            'metric' => 'events',
            'group' => 'role',
            'top' => 10000,
        ]);

        $this->assertSame('editor', $filters['group']);
        $this->assertSame(10, $filters['top']);
    }

    public function test_slot_type_filter_keeps_user_group_for_member_ranking(): void
    {
        $filters = (new PublicStatisticsService())->normalized([
            'metric' => 'participations',
            'group' => 'user',
            'role' => 27,
            'period' => '6m',
            'platform' => 2,
            'top' => 20,
        ]);

        $this->assertSame('participations', $filters['metric']);
        $this->assertSame('user', $filters['group']);
        $this->assertSame(27, $filters['role']);
        $this->assertSame('6m', $filters['period']);
        $this->assertSame(2, $filters['platform']);
        $this->assertSame(20, $filters['top']);
    }

    public function test_role_category_defaults_to_player_ranking(): void
    {
        $filters = (new PublicStatisticsService())->normalized(['focus' => 'role', 'role' => 27]);
        $this->assertSame('role', $filters['focus']);
        $this->assertSame('participations', $filters['metric']);
        $this->assertSame('user', $filters['group']);
        $this->assertSame(27, $filters['role']);
    }

    public function test_activity_type_category_defaults_to_events_by_type(): void
    {
        $filters = (new PublicStatisticsService())->normalized(['focus' => 'activity_type', 'activity_type' => 2]);
        $this->assertSame('activity_type', $filters['focus']);
        $this->assertSame('events', $filters['metric']);
        $this->assertSame('activity_type', $filters['group']);
        $this->assertSame(2, $filters['activity_type']);
    }

    public function test_editor_category_defaults_to_missions_by_editor(): void
    {
        $filters = (new PublicStatisticsService())->normalized(['focus' => 'editor', 'editor' => 12]);
        $this->assertSame('editor', $filters['focus']);
        $this->assertSame('missions', $filters['metric']);
        $this->assertSame('editor', $filters['group']);
    }

    public function test_previous_role_ranking_link_infers_role_category(): void
    {
        $filters = (new PublicStatisticsService())->normalized(['metric' => 'participations', 'group' => 'role']);
        $this->assertSame('role', $filters['focus']);
        $this->assertSame('role', $filters['group']);
    }

    public function test_member_profile_links_keep_user_as_primary_category(): void
    {
        $filters = (new PublicStatisticsService())->normalized([
            'metric' => 'participations', 'group' => 'role', 'user' => 62,
        ]);
        $this->assertSame('user', $filters['focus']);
        $this->assertSame('role', $filters['group']);
    }

    public function test_custom_period_and_optional_filters_are_preserved(): void
    {
        $filters = (new PublicStatisticsService())->normalized([
            'metric' => 'participations',
            'group' => 'role',
            'period' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-08-31',
            'platform' => 4,
            'user' => 81,
        ]);

        $this->assertSame('role', $filters['group']);
        $this->assertSame('custom', $filters['period']);
        $this->assertSame('2026-01-01', $filters['from']);
        $this->assertSame(4, $filters['platform']);
        $this->assertSame(81, $filters['user']);
    }
}
