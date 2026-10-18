<?php

namespace Tests\Unit;

use App\Services\EventOrbatSyncService;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;
use ReflectionMethod;
use Tests\TestCase;

class EventOrbatVisibilityRulesTest extends TestCase
{
    public function test_event_sync_does_not_require_a_specific_slot_type(): void
    {
        // El ORBAT del evento puede no tener el slot obligatorio del tipo de actividad.
        $orbat = [
            'groups' => [[
                'name' => 'ALPHA',
                'visible' => false,
                'slots' => [
                    ['slot_key' => 'slot-1', 'slot_type_id' => 2, 'visible' => false],
                ],
            ]],
        ];

        $slotMap = new Collection([
            'slot-1' => ['slot_type_id' => 2, 'faction_id' => 1],
        ]);

        $this->validateSyncSchema($orbat, $slotMap);
        $this->assertTrue(true);
    }

    public function test_event_sync_still_rejects_duplicate_slot_keys(): void
    {
        $orbat = [
            'groups' => [[
                'slots' => [
                    ['slot_key' => 'duplicated', 'slot_type_id' => 2],
                    ['slot_key' => 'duplicated', 'slot_type_id' => 2],
                ],
            ]],
        ];

        $this->expectException(ValidationException::class);
        $this->validateSyncSchema($orbat, new Collection([
            'duplicated' => ['slot_type_id' => 2, 'faction_id' => 1],
        ]));
    }

    public function test_activity_keeps_required_slot_rule_but_event_forms_do_not(): void
    {
        $project = dirname(__DIR__, 2);
        $activitySource = file_get_contents($project . '/app/Filament/Resources/Activities/Pages/EditActivity.php');
        $createSource = file_get_contents($project . '/app/Filament/Resources/Events/Pages/CreateEvent.php');
        $editSource = file_get_contents($project . '/app/Filament/Resources/Events/Pages/EditEvent.php');

        $this->assertStringContainsString('OrbatGlobalCommand::hasRequirement', $activitySource);
        $this->assertStringNotContainsString('OrbatGlobalCommand::', $createSource);
        $this->assertStringNotContainsString('OrbatGlobalCommand::', $editSource);
    }

    private function validateSyncSchema(array $orbat, Collection $slotMap): void
    {
        $method = new ReflectionMethod(EventOrbatSyncService::class, 'assertValidSlotKeys');
        $method->invoke(new EventOrbatSyncService(), $orbat, $slotMap);
    }
}
