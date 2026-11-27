<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\EventOrbatMarkingService;
use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesEventRegressionSchema;
use Tests\TestCase;

class EventOrbatMarkingsTest extends TestCase
{
    use CreatesEventRegressionSchema;

    protected function setUp(): void
    {
        parent::setUp();
        $this->createEventRegressionSchema();
        $this->seedBasicEventRegressionData();
        $this->insertVerifiedUser(10, 'Lider');
        $this->insertVerifiedUser(11, 'Compañero');
        $this->insertVerifiedUser(12, 'Otro grupo');
        $this->insertActiveEvent();

        DB::table('slot_types')->where('id', 1)->update(['orbat_marking_permission' => 'group']);
        DB::table('events')->where('id', 1)->update(['orbat' => json_encode([
            'groups' => [
                ['name' => 'Alpha', 'visible' => true, 'faction_id' => 1, 'slots' => [
                    ['slot_key' => 'slot-alpha-1', 'name' => 'Líder', 'slot_type_id' => 1, 'visible' => true],
                    ['slot_key' => 'slot-alpha-2', 'name' => 'Fusilero', 'slot_type_id' => 1, 'visible' => true],
                    ['slot_key' => 'slot-alpha-3', 'name' => 'Reserva de slot', 'slot_type_id' => 1, 'visible' => true],
                ]],
                ['name' => 'Bravo', 'visible' => true, 'faction_id' => 1, 'slots' => [
                    ['slot_key' => 'slot-bravo-1', 'name' => 'Fusilero', 'slot_type_id' => 1, 'visible' => true],
                ]],
            ],
        ])]);

        foreach ([
            ['slot-alpha-1', 10, 'Alpha'],
            ['slot-alpha-2', 11, 'Alpha'],
            ['slot-bravo-1', 12, 'Bravo'],
        ] as [$key, $user, $group]) {
            DB::table('event_slots')->insert([
                'event_id' => 1, 'slot_key' => $key, 'user_id' => $user,
                'name' => 'Fusilero', 'slot_type_id' => 1,
                'slot_group' => $group, 'faction_id' => 1,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function alphaItems(?int $first = 1, string $color = 'green'): array
    {
        return [
            ['slot_key' => 'slot-alpha-1', 'number' => $first, 'color' => $color],
            ['slot_key' => 'slot-alpha-2', 'number' => 2, 'color' => 'yellow'],
            ['slot_key' => 'slot-alpha-3', 'number' => null, 'color' => 'white'],
        ];
    }

    public function test_squad_leader_can_save_numbers_and_colors_and_notify_only_squad_mates(): void
    {
        $this->actingAs(User::findOrFail(10))
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Alpha', 'items' => $this->alphaItems(),
            ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('event_orbat_markings', [
            'event_id' => 1, 'participant_key' => 'user:10',
            'number' => 1, 'color' => 'green',
        ]);
        $this->assertDatabaseHas('event_orbat_marking_notices', [
            'event_id' => 1, 'user_id' => 11, 'actor_id' => 10, 'group_name' => 'Alpha',
        ]);
        $this->assertDatabaseMissing('event_orbat_marking_notices', ['user_id' => 10]);
        $this->assertDatabaseMissing('event_orbat_marking_notices', ['user_id' => 12]);
        $this->assertDatabaseCount('event_orbat_marking_notices', 1);
        $this->assertDatabaseHas('event_orbat_marking_history', [
            'event_id' => 1, 'actor_id' => 10, 'participant_key' => 'user:10',
            'old_color' => 'white', 'new_color' => 'green',
        ]);
        $this->assertDatabaseCount('event_orbat_marking_history', 2);

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $this->alphaItems(),
        ])->assertOk()->assertJsonPath('changed', false);
        $this->assertDatabaseCount('event_orbat_marking_notices', 1);
        $this->assertDatabaseCount('event_orbat_marking_history', 2);
    }

    public function test_leader_cannot_edit_other_groups_and_non_leaders_cannot_edit(): void
    {
        $this->actingAs(User::findOrFail(10))
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Bravo', 'items' => [
                    ['slot_key' => 'slot-bravo-1', 'number' => 1, 'color' => 'red'],
                ],
            ])->assertUnprocessable();

        DB::table('slot_types')->where('id', 1)->update(['orbat_marking_permission' => 'none']);
        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $this->alphaItems(),
        ])->assertUnprocessable();
        $this->assertDatabaseCount('event_orbat_markings', 0);
    }

    public function test_invalid_colors_duplicate_numbers_and_vacant_slots_are_rejected(): void
    {
        $user = User::findOrFail(10);
        $duplicate = $this->alphaItems();
        $duplicate[1]['number'] = 1;
        $this->actingAs($user)->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $duplicate,
        ])->assertUnprocessable();

        $invalid = $this->alphaItems();
        $invalid[0]['color'] = 'purple';
        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $invalid,
        ])->assertUnprocessable();

        $vacant = $this->alphaItems();
        $vacant[2]['number'] = 3;
        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $vacant,
        ])->assertUnprocessable();
        $this->assertDatabaseCount('event_orbat_markings', 0);
    }

    public function test_number_and_color_follow_member_when_they_change_slot(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $actor = User::findOrFail(10);
        $service->save(Event::findOrFail(1), $actor, 'Alpha', $this->alphaItems());
        DB::table('event_slots')->where('user_id', 10)->update(['slot_key' => 'slot-alpha-3']);
        $marks = $service->markings(Event::findOrFail(1));

        $this->assertSame(['number' => 1, 'color' => 'green'], $marks['slot-alpha-3']);
        $this->assertArrayNotHasKey('slot-alpha-1', $marks);
    }

    public function test_global_permission_allows_editing_other_groups(): void
    {
        DB::table('slot_types')->where('id', 1)->update(['orbat_marking_permission' => 'all']);
        $this->actingAs(User::findOrFail(10))
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Bravo', 'items' => [
                    ['slot_key' => 'slot-bravo-1', 'number' => 1, 'color' => 'blue'],
                ],
            ])->assertOk();

        $this->assertDatabaseHas('event_orbat_markings', [
            'participant_key' => 'user:12', 'color' => 'blue',
        ]);
        $this->assertDatabaseHas('event_orbat_marking_notices', ['user_id' => 12]);
    }
}
