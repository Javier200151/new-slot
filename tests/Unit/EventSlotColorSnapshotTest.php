<?php

namespace Tests\Unit;

use App\Models\EventSlot;
use Tests\TestCase;

class EventSlotColorSnapshotTest extends TestCase
{
    public function test_empty_slot_has_no_snapshot(): void
    {
        $slot = new EventSlot(['user_id' => null, 'status_color_snapshot' => '#abcdef']);
        $slot->syncOccupantColor();
        $this->assertNull($slot->status_color_snapshot);
    }

    public function test_legacy_snapshot_remains_readable(): void
    {
        $slot = new EventSlot(['status_color_snapshot' => '#aabbcc']);
        $this->assertSame('#aabbcc', $slot->occupantStatusColor());
    }
}
