<?php

namespace Tests\Unit;

use App\Services\MissionControl\MissionControlTelegramMonitor;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class MissionControlTelegramScheduleTest extends TestCase
{
    public function test_default_two_hour_checkpoints_until_half_an_hour_before_event(): void
    {
        $start = CarbonImmutable::parse('2026-11-25 22:30:00', 'Europe/Madrid');
        $due = static fn (int $minutes): ?int => MissionControlTelegramMonitor::checkpointAt($start->subMinutes($minutes), $start, 120);

        self::assertNull($due(121));
        self::assertSame(120, $due(120));
        self::assertSame(120, $due(91));
        self::assertSame(90, $due(90));
        self::assertSame(60, $due(60));
        self::assertSame(30, $due(30));
        self::assertSame(30, $due(16));
        self::assertNull($due(15));
    }

    public function test_custom_lead_always_includes_the_last_thirty_minute_check(): void
    {
        $start = CarbonImmutable::parse('2026-11-25 22:30:00', 'Europe/Madrid');
        $due = static fn (int $minutes): ?int => MissionControlTelegramMonitor::checkpointAt($start->subMinutes($minutes), $start, 105);

        self::assertSame(105, $due(105));
        self::assertSame(75, $due(75));
        self::assertSame(45, $due(45));
        self::assertSame(30, $due(30));
        self::assertNull($due(15));
    }
}
