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

        // La API pública devuelve únicamente grupos visibles y datos públicos.
        $response
            ->assertOk()
            ->assertJsonPath('data.evento_id', 1)
            ->assertJsonPath('data.grupos_visibles.0.nombre', 'Alpha')
            ->assertJsonPath('data.grupos_visibles.0.slots.0.nombre', 'Fusilero 1')
            ->assertJsonPath('data.grupos_visibles.0.slots.0.ocupado', true)
            ->assertJsonPath('data.grupos_visibles.0.slots.0.ocupante.nick', 'Assigned User')
            ->assertJsonPath('data.resumen.grupos', 1)
            ->assertJsonPath('data.resumen.slots', 1)
            ->assertJsonPath('data.resumen.ocupados', 1);

        // Los metadatos internos de reservas, identificadores y aliados no se publican.
        $response->assertJsonMissingPath('data.reservations')
            ->assertJsonMissingPath('data.orbat.unmatched_assignments');
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
            ->assertJsonPath('data.evento_id', 1)
            ->assertJsonCount(0, 'data.grupos_visibles')
            ->assertJsonPath('data.resumen.slots', 0)
            ->assertJsonPath('data.resumen.ocupados', 0);
    }
}
