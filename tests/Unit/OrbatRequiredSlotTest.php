<?php

namespace Tests\Unit;

use App\Models\ActivityType;
use App\Models\SlotType;
use App\Support\OrbatGlobalCommand;
use PHPUnit\Framework\TestCase;

class OrbatRequiredSlotTest extends TestCase
{
    public function test_activity_type_can_have_no_required_orbat_slot(): void
    {
        $type = new ActivityType(['name' => 'Instrucción']);
        $type->required_orbat_slot_type_id = null;

        $this->assertFalse(OrbatGlobalCommand::hasRequirement($type));
        $this->assertSame(0, OrbatGlobalCommand::count(['groups' => []], $type));
    }

    public function test_only_selected_slot_type_counts_as_required(): void
    {
        $type = new ActivityType(['name' => 'Operativo']);
        $type->required_orbat_slot_type_id = 8;
        $type->setRelation('requiredOrbatSlotType', new SlotType(['name' => 'Mando Global']));

        $orbat = [
            'groups' => [[
                'name' => 'HQ',
                'slots' => [
                    ['slot_type_id' => 8],
                    ['slot_type_id' => 4],
                    ['slot_type_id' => 8],
                ],
            ]],
        ];

        $this->assertTrue(OrbatGlobalCommand::hasRequirement($type));
        $this->assertSame(2, OrbatGlobalCommand::count($orbat, $type));
        $this->assertSame('Mando Global', OrbatGlobalCommand::requiredSlotName($type));
        $this->assertSame(
            '¿Estás seguro de que quieres colocar un segundo Mando Global?',
            OrbatGlobalCommand::secondSlotQuestion($type),
        );
    }
}
