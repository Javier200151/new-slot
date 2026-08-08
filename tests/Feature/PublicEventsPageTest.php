<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicEventsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('event_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('event_results', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('operation_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('operations_type', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
        });

        Schema::create('periods', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('ico')->nullable();
        });

        Schema::create('platforms', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
        });

        Schema::create('operation_day', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->softDeletes();
        });

        Schema::create('allies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('armies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('sides', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('factions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('army_id')->nullable();
            $table->foreignId('side_id')->nullable();
            $table->string('name');
        });

        Schema::create('slot_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('addons', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('mandatory')->default(false);
            $table->boolean('active')->default(true);
        });

        Schema::create('campaign', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
        });

        Schema::create('operations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_type_id');
            $table->foreignId('operation_status_id')->nullable();
            $table->foreignId('period_id')->nullable();
            $table->foreignId('platform_id')->nullable();
            $table->foreignId('map_id')->nullable();
            $table->foreignId('campaign_id')->nullable();
            $table->foreignId('day_id')->nullable();
            $table->foreignId('editor_id')->nullable();
            $table->string('name');
            $table->string('image')->nullable();
            $table->json('description')->nullable();
            $table->json('radio')->nullable();
            $table->json('addons')->nullable();
            $table->boolean('ocap')->default(false);
            $table->boolean('respawn')->default(false);
            $table->boolean('jip')->default(false);
            $table->string('pbo')->nullable();
            $table->string('day_or_night')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('enemy_faction_operation', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faction_id');
            $table->foreignId('operation_id');
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('operation_id');
            $table->foreignId('event_status_id');
            $table->foreignId('event_result_id')->nullable();
            $table->string('name');
            $table->dateTime('date');
            $table->unsignedInteger('duration')->nullable();
            $table->json('orbat')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_slots', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id');
            $table->string('slot_key')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->foreignId('ally_id')->nullable();
            $table->string('name')->nullable();
            $table->foreignId('slot_type_id')->nullable();
            $table->string('slot_group')->nullable();
            $table->foreignId('faction_id')->nullable();
        });

        DB::table('event_status')->insert([
            ['id' => 1, 'name' => 'ACTIVO'],
            ['id' => 2, 'name' => 'FINALIZADO'],
            ['id' => 3, 'name' => 'BORRADOR'],
        ]);

        DB::table('event_results')->insert([
            ['id' => 1, 'name' => 'ÉXITO'],
        ]);

        DB::table('operation_status')->insert([
            ['id' => 1, 'name' => 'ACTIVO'],
        ]);

        DB::table('operations_type')->insert([
            ['id' => 1, 'name' => 'Oficial', 'color' => '#f59e0b'],
            ['id' => 2, 'name' => 'Prácticas', 'color' => '#22c55e'],
        ]);

        DB::table('periods')->insert([
            ['id' => 1, 'name' => 'Moderna', 'ico' => 'periods/moderna.png'],
        ]);

        DB::table('platforms')->insert([
            ['id' => 1, 'name' => 'Arma 3', 'image' => 'platforms/arma-3.png'],
        ]);

        DB::table('campaign')->insert([
            [
                'id' => 1,
                'name' => 'Campaña Centinela',
                'description' => 'Operaciones coordinadas de la campaña.',
            ],
        ]);

        DB::table('operations')->insert([
            [
                'id' => 1,
                'operation_type_id' => 1,
                'operation_status_id' => 1,
                'period_id' => 1,
                'platform_id' => 1,
                'campaign_id' => null,
                'name' => 'Operación Alpha',
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
            [
                'id' => 2,
                'operation_type_id' => 2,
                'operation_status_id' => 1,
                'period_id' => 1,
                'platform_id' => 1,
                'campaign_id' => 1,
                'name' => 'Operación Bravo',
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
            [
                'id' => 3,
                'operation_type_id' => 1,
                'operation_status_id' => 1,
                'period_id' => 1,
                'platform_id' => 1,
                'campaign_id' => 1,
                'name' => 'Operación sin evento',
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
        ]);

        DB::table('events')->insert([
            [
                'id' => 1,
                'operation_id' => 1,
                'event_status_id' => 1,
                'event_result_id' => null,
                'name' => 'Evento activo',
                'date' => '2026-08-10 21:00:00',
                'duration' => 120,
                'orbat' => json_encode([
                    'groups' => [
                        [
                            'slots' => [
                                ['slot_key' => 'slot-1'],
                                ['slot_key' => 'slot-2'],
                            ],
                        ],
                        [
                            'slots' => [
                                ['slot_key' => 'slot-3'],
                                ['slot_key' => 'slot-4'],
                            ],
                        ],
                    ],
                ]),
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
            [
                'id' => 2,
                'operation_id' => 2,
                'event_status_id' => 2,
                'event_result_id' => 1,
                'name' => 'Evento finalizado',
                'date' => '2026-08-20 20:00:00',
                'duration' => 90,
                'orbat' => null,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
            [
                'id' => 3,
                'operation_id' => 1,
                'event_status_id' => 3,
                'event_result_id' => null,
                'name' => 'Evento borrador',
                'date' => '2026-08-25 20:00:00',
                'duration' => 90,
                'orbat' => null,
                'created_at' => '2026-08-01 10:00:00',
                'updated_at' => '2026-08-01 10:00:00',
            ],
        ]);

        DB::table('event_slots')->insert([
            ['event_id' => 1, 'user_id' => 10, 'ally_id' => null],
            ['event_id' => 1, 'user_id' => null, 'ally_id' => 20],
            ['event_id' => 1, 'user_id' => null, 'ally_id' => null],
        ]);
    }

    public function test_calendar_lists_only_active_and_finished_events_for_the_selected_month(): void
    {
        $response = $this->get('/eventos?month=8&year=2026');

        $response
            ->assertOk()
            ->assertSee('Agosto 2026')
            ->assertSee('Evento activo')
            ->assertSee('Evento finalizado')
            ->assertDontSee('Evento borrador')
            ->assertSee('Resultado')
            ->assertSee('ÉXITO')
            ->assertSee('Campaña')
            ->assertSee('Campaña Centinela')
            ->assertSee(route('campaigns.show', 1), escape: false)
            ->assertSee(route('events.show', 1), escape: false)
            ->assertSee('2 / 4')
            ->assertSee('Lunes 10/08/26 21:00H')
            ->assertSee('Jueves 20/08/26 20:00H')
            ->assertSee('name="date_from" value="2026-08-01"', escape: false)
            ->assertSee('name="date_to" value="2026-08-31"', escape: false)
            ->assertSee('storage/periods/moderna.png', escape: false)
            ->assertSee('storage/platforms/arma-3.png', escape: false)
            ->assertSee('Plataforma Arma 3')
            ->assertSeeInOrder(['id="evento-2"', 'id="evento-1"'], escape: false);
    }

    public function test_event_list_can_be_filtered_by_operation_type_and_date(): void
    {
        $response = $this->get('/eventos?month=8&year=2026&type=1&date_from=2026-08-10&date_to=2026-08-10');

        $response
            ->assertOk()
            ->assertSee('id="evento-1"', escape: false)
            ->assertDontSee('id="evento-2"', escape: false)
            ->assertSee('1 evento encontrado');
    }

    public function test_campaign_page_shows_only_active_or_finished_associated_events(): void
    {
        $response = $this->get('/campanas/1');

        $response
            ->assertOk()
            ->assertSee('Campaña Centinela')
            ->assertSee('Operaciones coordinadas de la campaña.')
            ->assertSee('1 evento')
            ->assertSee('Evento finalizado')
            ->assertDontSee('Evento activo')
            ->assertDontSee('Evento borrador')
            ->assertDontSee('Operación sin evento')
            ->assertSee('Prácticas')
            ->assertSee('FINALIZADO')
            ->assertSee('storage/periods/moderna.png', escape: false)
            ->assertSee('storage/platforms/arma-3.png', escape: false);
    }

    public function test_campaign_page_preserves_rich_text_colors(): void
    {
        DB::table('campaign')->where('id', 1)->update([
            'description' => '<p><span class="color" data-color="red">Alerta roja</span></p>',
        ]);

        $this->get('/campanas/1')
            ->assertOk()
            ->assertSee('Alerta roja')
            ->assertSee('class="color"', escape: false)
            ->assertSee('data-color="red"', escape: false)
            ->assertSee('--color:', escape: false);

        $this->assertStringContainsString(
            '.campaign-page__description .color',
            file_get_contents(public_path('css/events.css')),
        );
        $this->assertStringContainsString(
            'color: var(--color);',
            file_get_contents(public_path('css/events.css')),
        );
    }

    public function test_event_page_shows_operation_data_and_only_visible_event_orbat(): void
    {
        DB::table('operation_day')->insert(['id' => 1, 'name' => 'Viernes']);
        DB::table('users')->insert(['id' => 10, 'nick' => 'Alfa Uno']);
        DB::table('armies')->insert(['id' => 1, 'name' => 'OTAN']);
        DB::table('sides')->insert(['id' => 1, 'name' => 'BLUFOR']);
        DB::table('factions')->insert([
            'id' => 1,
            'army_id' => 1,
            'side_id' => 1,
            'name' => 'US Army',
        ]);
        DB::table('slot_types')->insert([
            ['id' => 1, 'name' => 'Líder'],
            ['id' => 2, 'name' => 'Fusilero'],
        ]);
        DB::table('addons')->insert([
            'id' => 1,
            'name' => 'ACE',
            'description' => 'Sistema médico avanzado.',
            'mandatory' => true,
            'active' => true,
        ]);
        DB::table('enemy_faction_operation')->insert([
            'faction_id' => 1,
            'operation_id' => 1,
        ]);

        DB::table('operations')->where('id', 1)->update([
            'day_id' => 1,
            'editor_id' => 10,
            'description' => json_encode([
                'sections' => [[
                    'title' => 'Situación',
                    'content' => '<p>Briefing visible del operativo.</p>',
                ]],
            ]),
            'radio' => json_encode([
                'networks' => [
                    ['name' => 'Mando', 'radio_model_name' => 'AN/PRC-152', 'visible' => true],
                    ['name' => 'Red secreta', 'radio_model_name' => 'Oculta', 'visible' => false],
                ],
            ]),
            'addons' => json_encode(['addon_ids' => [1]]),
            'ocap' => true,
            'respawn' => false,
            'jip' => true,
            'pbo' => 'operacion_alpha.pbo',
            'day_or_night' => 'night',
        ]);

        DB::table('events')->where('id', 1)->update([
            'orbat' => json_encode([
                'groups' => [
                    [
                        'name' => 'Alpha',
                        'faction_id' => 1,
                        'visible' => true,
                        'slots' => [
                            ['slot_key' => 'slot-visible', 'name' => 'Alpha 1', 'slot_type_id' => 1, 'visible' => true],
                            ['slot_key' => 'slot-hidden', 'name' => 'Alpha oculto', 'slot_type_id' => 2, 'visible' => false],
                        ],
                    ],
                    [
                        'name' => 'Grupo secreto',
                        'faction_id' => 1,
                        'visible' => false,
                        'slots' => [
                            ['slot_key' => 'secret-slot', 'name' => 'Slot secreto', 'slot_type_id' => 2, 'visible' => true],
                        ],
                    ],
                ],
            ]),
        ]);

        DB::table('event_slots')
            ->where('event_id', 1)
            ->where('user_id', 10)
            ->update([
                'slot_key' => 'slot-visible',
                'name' => 'Alpha 1',
                'slot_type_id' => 1,
                'slot_group' => 'Alpha',
                'faction_id' => 1,
            ]);

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Evento activo')
            ->assertSee('Operación Alpha')
            ->assertSee('Briefing visible del operativo.')
            ->assertSee('Mando')
            ->assertDontSee('Red secreta')
            ->assertSee('ACE')
            ->assertSee('US Army')
            ->assertSee('Alpha 1')
            ->assertSee('Alfa Uno')
            ->assertDontSee('Alpha oculto')
            ->assertDontSee('Grupo secreto')
            ->assertDontSee('Slot secreto')
            ->assertSeeInOrder([
                'Briefing visible del operativo.',
                'ORBAT',
                'Mando',
                'ACE',
            ]);

        $this->assertStringContainsString(
            ".event-orbat__slots {\n    display: grid;\n    grid-template-columns: 1fr;",
            file_get_contents(public_path('css/events.css')),
        );
    }

    public function test_draft_event_page_is_not_public(): void
    {
        $this->get('/eventos/3')->assertNotFound();
    }
}
