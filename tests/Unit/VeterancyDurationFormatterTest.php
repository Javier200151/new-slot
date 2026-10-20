<?php

namespace Tests\Unit;

use App\Services\VeterancyService;
use PHPUnit\Framework\TestCase;

class VeterancyDurationFormatterTest extends TestCase
{
    public function test_accumulated_day_counts_are_formatted_consistently(): void
    {
        $this->assertSame('0a 0m 0d', VeterancyService::formatAccumulatedDays(0));
        $this->assertSame('0a 0m 1d', VeterancyService::formatAccumulatedDays(1));
        $this->assertSame('0a 1m 0d', VeterancyService::formatAccumulatedDays(30));
        $this->assertSame('1a 0m 0d', VeterancyService::formatAccumulatedDays(365));
        $this->assertSame('2a 3m 10d', VeterancyService::formatAccumulatedDays(830));
        $this->assertSame('2a 4m 8d', VeterancyService::formatAccumulatedDays(858));
        $this->assertSame('0a 0m 0d', VeterancyService::formatAccumulatedDays(-10));
    }
}
