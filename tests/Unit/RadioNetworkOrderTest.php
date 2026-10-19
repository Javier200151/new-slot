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

    public function test_unsaved_numeric_repeater_keys_reorder_without_integer_rows(): void
    {
        $rows = [
            0 => ['name' => 'Global'],
            1 => ['name' => 'Bravo'],
            2 => ['name' => 'Aire'],
        ];

        $reordered = RadioNetworkOrder::reorderRepeaterItems($rows, ['2', '0', '1']);

        $this->assertSame([2, 0, 1], array_keys($reordered));
        $this->assertSame(['Aire', 'Global', 'Bravo'], array_column($reordered, 'name'));
        $this->assertSame($rows[2], $reordered[2]);
        $this->assertSame($rows[0], $reordered[0]);
        $this->assertSame($rows[1], $reordered[1]);
    }

    public function test_uuid_repeater_keys_are_reordered_without_changing_their_values(): void
    {
        $rows = [
            'radio-a' => ['name' => 'Alpha', 'configuration' => ['block' => 11]],
            'radio-b' => ['name' => 'Bravo', 'configuration' => ['block' => 21]],
        ];

        $reordered = RadioNetworkOrder::reorderRepeaterItems($rows, ['radio-b', 'radio-a']);

        $this->assertSame(['radio-b', 'radio-a'], array_keys($reordered));
        $this->assertSame($rows['radio-b'], $reordered['radio-b']);
        $this->assertSame($rows['radio-a'], $reordered['radio-a']);
    }

    public function test_stale_sort_keys_do_not_inject_non_network_items(): void
    {
        $rows = [
            0 => ['name' => 'Global'],
            1 => ['name' => 'Aire'],
        ];

        $reordered = RadioNetworkOrder::reorderRepeaterItems(
            $rows,
            ['outdated-browser-id', '1'],
        );

        $this->assertSame([1, 0], array_keys($reordered));
        $this->assertSame(['Aire', 'Global'], array_column($reordered, 'name'));
        $this->assertContainsOnlyArray(array_values($reordered));
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
