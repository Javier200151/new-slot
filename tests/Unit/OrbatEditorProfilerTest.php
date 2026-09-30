<?php

namespace Tests\Unit;

use App\Support\OrbatEditorProfiler;
use PHPUnit\Framework\TestCase;

class OrbatEditorProfilerTest extends TestCase
{
    public function test_it_counts_groups_slots_visibility_and_slot_types_without_mutating_orbat(): void
    {
        $orbat = [
            'groups' => [
                [
                    'name' => 'Alpha',
                    'visible' => true,
                    'slots' => [
                        ['slot_type_id' => 4, 'visible' => true],
                        ['slot_type_id' => 4, 'visible' => false],
                    ],
                ],
                [
                    'name' => 'Bravo',
                    'visible' => false,
                    'slots' => [
                        ['slot_type_id' => 8],
                    ],
                ],
            ],
        ];
        $before = $orbat;

        $metrics = OrbatEditorProfiler::analyze($orbat);

        $this->assertSame(2, $metrics['groups']);
        $this->assertSame(1, $metrics['visible_groups']);
        $this->assertSame(3, $metrics['slots']);
        $this->assertSame(2, $metrics['visible_slots']);
        $this->assertSame(1, $metrics['hidden_slots']);
        $this->assertSame(2, $metrics['slot_types']);
        $this->assertGreaterThan(0, $metrics['json_bytes']);
        $this->assertSame($before, $orbat);
    }


    public function test_ini_bytes_understands_php_memory_limit_values(): void
    {
        $this->assertSame(256 * 1024 * 1024, OrbatEditorProfiler::iniBytes('256M'));
        $this->assertSame(2 * 1024 * 1024 * 1024, OrbatEditorProfiler::iniBytes('2G'));
        $this->assertSame(-1, OrbatEditorProfiler::iniBytes('-1'));
        $this->assertNull(OrbatEditorProfiler::iniBytes('invalid'));
    }

    public function test_bytes_formats_memory_values_for_the_profile_command(): void
    {
        $this->assertSame('512 B', OrbatEditorProfiler::bytes(512));
        $this->assertSame('2.0 KiB', OrbatEditorProfiler::bytes(2048));
        $this->assertSame('2.0 MiB', OrbatEditorProfiler::bytes(2 * 1024 * 1024));
    }
}
