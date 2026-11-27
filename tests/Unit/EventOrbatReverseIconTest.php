<?php

namespace Tests\Unit;

use Tests\TestCase;

class EventOrbatReverseIconTest extends TestCase
{
    public function test_tactical_reverse_reuses_the_normal_slot_type_icon_asset(): void
    {
        $view = file_get_contents(resource_path('views/events/show.blade.php'));

        $this->assertIsString($view);
        $this->assertStringContainsString('class="orbat-marking-role"', $view);
        $this->assertStringContainsString("asset('storage/' . \$markingSlot['slot_type']->image)", $view);
        $this->assertStringContainsString('class="event-orbat__slot-type-icon"', $view);
        $this->assertStringContainsString("asset('storage/' . \$slot['slot_type']->image)", $view);
    }
}
