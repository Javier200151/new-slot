<?php

namespace Tests\Unit;

use App\Models\Activity;
use App\Support\PublicApiData;
use Tests\TestCase;

class PublicActivityGameOptionsTest extends TestCase
{
    public function test_exposes_three_real_boolean_values(): void
    {
        $activity = new Activity(['ocap' => 1, 'respawn' => 0, 'jip' => true]);
        $this->assertSame([
            'ocap' => true,
            'respawn' => false,
            'jip' => true,
        ], PublicApiData::activityGameOptions($activity));
    }

    public function test_disabled_options_and_missing_activity_do_not_become_null(): void
    {
        $expected = ['ocap' => false, 'respawn' => false, 'jip' => false];
        $this->assertSame($expected, PublicApiData::activityGameOptions(new Activity()));
        $this->assertSame($expected, PublicApiData::activityGameOptions(null));
    }
}
