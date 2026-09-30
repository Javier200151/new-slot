<?php

namespace Tests\Feature;

use App\Http\Controllers\CommunityDiaryController;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use ReflectionMethod;
use Tests\Support\CreatesEventRegressionSchema;
use Tests\TestCase;

class CommunityDiaryRegressionTest extends TestCase
{
    use CreatesEventRegressionSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEventRegressionSchema();
        $this->seedBasicEventRegressionData();
        $this->insertVerifiedUser(10, 'Diary User');
        $this->insertActiveEvent();
    }

    public function test_latest_unassignment_does_not_resurrect_an_old_event_as_participated(): void
    {
        DB::table('event_slot_history')->insert([
            [
                'event_id' => 1,
                'user_id' => 10,
                'action' => 'assigned',
                'to_slot_key' => 'slot-alpha-1',
                'to_slot_name' => 'Fusilero 1',
                'to_slot_group' => 'Alpha',
                'created_at' => now()->subMinutes(2),
            ],
            [
                'event_id' => 1,
                'user_id' => 10,
                'action' => 'unassigned',
                'from_slot_key' => 'slot-alpha-1',
                'from_slot_name' => 'Fusilero 1',
                'from_slot_group' => 'Alpha',
                'created_at' => now()->subMinute(),
            ],
        ]);

        $this->assertFalse($this->participatedEventIds(10)->contains(1));
    }

    public function test_latest_move_destination_is_a_valid_diary_participation_fallback(): void
    {
        DB::table('event_slot_history')->insert([
            'event_id' => 1,
            'user_id' => 10,
            'action' => 'moved',
            'from_slot_key' => 'slot-old',
            'from_slot_name' => 'Antiguo',
            'from_slot_group' => 'Bravo',
            'to_slot_key' => 'slot-alpha-1',
            'to_slot_name' => 'Fusilero 1',
            'to_slot_group' => 'Alpha',
            'created_at' => now(),
        ]);

        $this->assertTrue($this->participatedEventIds(10)->contains(1));
    }

    public function test_current_event_slot_is_always_considered_participation(): void
    {
        DB::table('event_slots')->insert([
            'event_id' => 1,
            'slot_key' => 'slot-alpha-1',
            'user_id' => 10,
            'name' => 'Fusilero 1',
            'slot_type_id' => 1,
            'slot_group' => 'Alpha',
            'faction_id' => 1,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertTrue($this->participatedEventIds(10)->contains(1));
    }

    private function participatedEventIds(int $userId): Collection
    {
        $controller = app(CommunityDiaryController::class);
        $method = new ReflectionMethod($controller, 'participatedEventIds');

        return $method->invoke($controller, $userId);
    }
}
