<?php

namespace Tests\Unit;

use App\Support\RadioNetworkOrder;
use PHPUnit\Framework\TestCase;

class RadioNetworkOrderTest extends TestCase
{
    public function test_saved_order_is_respected_when_loading(): void
    {
        $networks = RadioNetworkOrder::ordered([
            ['name' => 'Bravo', 'order' => 2],
            ['name' => 'Alpha', 'order' => 1],
            ['name' => 'Charlie', 'order' => 3],
        ]);

        $this->assertSame(
            ['Alpha', 'Bravo', 'Charlie'],
            array_column($networks, 'name')
        );
    }

    public function test_visual_repeater_order_is_persisted_explicitly(): void
    {
        $stored = RadioNetworkOrder::forStorage([
            ['name' => 'Global'],
            ['name' => 'Alpha 1-1'],
            ['name' => 'Aire'],
        ]);

        $this->assertSame([1, 2, 3], array_column($stored, 'order'));
        $this->assertSame(
            ['Global', 'Alpha 1-1', 'Aire'],
            array_column(RadioNetworkOrder::ordered($stored), 'name')
        );
    }

    public function test_legacy_networks_without_order_keep_existing_array_order(): void
    {
        $networks = RadioNetworkOrder::ordered([
            ['name' => 'Zeta'],
            ['name' => 'Alpha'],
        ]);

        $this->assertSame(['Zeta', 'Alpha'], array_column($networks, 'name'));
    }
}
