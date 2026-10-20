<?php

namespace Tests\Unit;

use App\Support\FallujahEasterEgg;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

class FallujahEasterEggScheduleTest extends TestCase
{
    public function test_auto_mode_is_visible_only_on_april_27_in_madrid(): void
    {
        $this->assertTrue(FallujahEasterEgg::visibleFor('auto', CarbonImmutable::parse('2027-04-27 12:00', 'Europe/Madrid')));
        $this->assertFalse(FallujahEasterEgg::visibleFor('auto', CarbonImmutable::parse('2027-04-26 23:59', 'Europe/Madrid')));
        $this->assertFalse(FallujahEasterEgg::visibleFor('auto', CarbonImmutable::parse('2027-04-28 00:00', 'Europe/Madrid')));
    }

    public function test_on_overrides_calendar_and_off_blocks_anniversary(): void
    {
        $this->assertTrue(FallujahEasterEgg::visibleFor('on', CarbonImmutable::parse('2026-10-20 19:00', 'Europe/Madrid')));
        $this->assertFalse(FallujahEasterEgg::visibleFor('off', CarbonImmutable::parse('2027-04-27 15:00', 'Europe/Madrid')));
    }

    public function test_calendar_uses_madrid_timezone_even_when_given_utc(): void
    {
        $this->assertTrue(FallujahEasterEgg::visibleFor('auto', CarbonImmutable::parse('2027-04-26 22:30', 'UTC')));
    }
}
