<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\DB;
use Tests\Support\CreatesEventRegressionSchema;
use Tests\TestCase;

class EventOrbatApiRegressionTest extends TestCase
{
    use CreatesEventRegressionSchema;

    protected function setUp(): void
    {
        parent::setUp();

        $this->createEventRegressionSchema();
        $this->seedBasicEventRegressionData();
        $this->insertVerifiedUser(10, 'Assigned User');
        $this->insertVerifiedUser(11, 'Reserved User', 3);
        $this->insertActiveEvent();
    }

    public function test_public_orbat_api_returns_current_assignments_reservations_and_no_cache_headers(): void
    {
        DB::table('event_slots')->insert([
            [
                'event_id' => 1,
                'slot_key' => 'slot-alpha-1',
                'user_id' => 10,
                'ally_id' => null,
                'name' => 'Fusilero 1',
                'slot_type_id' => 1,
                'slot_group' => 'Alpha',
                'faction_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'event_id' => 1,
                'slot_key' => 'legacy-unmatched',
                'user_id' => null,
                'ally_id' => 20,
                'name' => 'Invitado',
                'slot_type_id' => 1,
                'slot_group' => 'Externo',
                'faction_id' => 1,
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ]);

        DB::table('allies')->insert([
            'id' => 20,
            'name' => 'Clan aliado',
        ]);

        DB::table('event_reservations')->insert([
            'event_id' => 1,
            'user_id' => 11,
            'created_by' => 11,
            'created_at' => now()->subMinute(),
            'updated_at' => now()->subMinute(),
        ]);

        $response = $this->getJson('/api/eventos/1/orbat');

        $response
            ->assertOk()
            ->assertJsonPath('data.event.id', 1)
            ->assertJsonPath('data.event.reservations_enabled', true)
            ->assertJsonPath('data.orbat.groups.0.name', 'Alpha')
            ->assertJsonPath('data.orbat.groups.0.slots.0.slot_key', 'slot-alpha-1')
            ->assertJsonPath('data.orbat.groups.0.slots.0.occupant.type', 'user')
            ->assertJsonPath('data.orbat.groups.0.slots.0.occupant.user.nick', 'Assigned User')
            ->assertJsonPath('data.reservations.0.position', 1)
            ->assertJsonPath('data.reservations.0.user.nick', 'Reserved User')
            ->assertJsonPath('data.counts.slots', 1)
            ->assertJsonPath('data.counts.occupied_in_orbat', 1)
            ->assertJsonPath('data.counts.assigned_total', 2)
            ->assertJsonPath('data.counts.unmatched_assignments', 1)
            ->assertJsonPath('data.counts.reservations', 1)
            ->assertJsonPath('data.orbat.unmatched_assignments.0.occupant.type', 'ally')
            ->assertJsonPath('data.orbat.unmatched_assignments.0.occupant.ally.name', 'Clan aliado');

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_api_exposes_visibility_flags_instead_of_silently_dropping_hidden_orbat_nodes(): void
    {
        $orbat = json_decode((string) DB::table('events')->where('id', 1)->value('orbat'), true);
        $orbat['groups'][0]['visible'] = false;
        $orbat['groups'][0]['slots'][0]['visible'] = false;

        DB::table('events')->where('id', 1)->update([
            'orbat' => json_encode($orbat),
        ]);

        $this->getJson('/api/eventos/1/orbat')
            ->assertOk()
            ->assertJsonPath('data.orbat.groups.0.visible', false)
            ->assertJsonPath('data.orbat.groups.0.slots.0.visible', false);
    }
}
