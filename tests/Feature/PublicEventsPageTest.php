<?php

namespace Tests\Feature;

use App\Models\User;
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

        Schema::create('maps', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('image')->nullable();
            $table->string('url')->nullable();
            $table->foreignId('platform_id')->nullable();
            $table->timestamps();
        });

        Schema::create('operation_day', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->foreignId('status_id')->nullable();
            $table->string('firma')->nullable();
            $table->string('image')->nullable();
            $table->softDeletes();
        });

        Schema::create('metopas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image');
            $table->softDeletes();
        });

        Schema::create('metopa_user', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('metopa_id');
            $table->foreignId('user_id');
            $table->date('assigned_at')->nullable();
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
        });

        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->foreignId('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
        });

        Schema::create('sqa_groups', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('sqa_group_users', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('sqa_group_id');
            $table->foreignId('user_id');
            $table->boolean('main')->default(false);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
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

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->softDeletes();
        });

        Schema::create('slot_types_status', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_type_id');
            $table->foreignId('status_id');
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
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'slot_key']);
        });

        Schema::create('event_slot_history', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_slot_id')->nullable();
            $table->foreignId('event_id')->nullable();
            $table->foreignId('ally_id')->nullable();
            $table->foreignId('user_id')->nullable();
            $table->string('action');
            $table->string('from_slot_key')->nullable();
            $table->string('from_slot_name')->nullable();
            $table->foreignId('from_slot_type_id')->nullable();
            $table->string('from_slot_group')->nullable();
            $table->foreignId('from_army_id')->nullable();
            $table->string('to_slot_key')->nullable();
            $table->string('to_slot_name')->nullable();
            $table->foreignId('to_slot_type_id')->nullable();
            $table->string('to_slot_group')->nullable();
            $table->foreignId('to_army_id')->nullable();
            $table->foreignId('changed_by_user_id')->nullable();
            $table->timestamp('created_at')->nullable();
        });

        Schema::create('event_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('user_id')->nullable();
            $table->foreignId('parent_id')->nullable();
            $table->text('comment');
            $table->boolean('is_pinned')->default(false);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
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

        DB::table('maps')->insert([
            'id' => 1,
            'name' => 'Altis',
            'description' => 'Isla mediterránea con amplias zonas urbanas y rurales.',
            'image' => 'maps/altis.jpg',
            'url' => 'https://example.com/maps/altis',
            'platform_id' => 1,
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
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
                'map_id' => 1,
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
                'map_id' => null,
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
                'map_id' => null,
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
            ->assertSee(route('maps.show', 1), escape: false)
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

    public function test_event_list_date_range_can_span_multiple_calendar_months(): void
    {
        DB::table('events')->insert([
            'id' => 4,
            'operation_id' => 1,
            'event_status_id' => 1,
            'event_result_id' => null,
            'name' => 'Evento de septiembre',
            'date' => '2026-09-05 21:30:00',
            'duration' => 120,
            'orbat' => null,
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);

        $this->get('/eventos?month=8&year=2026&date_from=2026-08-20&date_to=2026-09-10')
            ->assertOk()
            ->assertSee('Agosto 2026')
            ->assertSee('Evento finalizado')
            ->assertSee('Evento de septiembre')
            ->assertSee('2 eventos encontrados')
            ->assertSeeInOrder(['id="evento-4"', 'id="evento-2"'], escape: false);
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

        DB::table('users')->where('id', 10)->update([
            'deleted_at' => '2026-08-09 12:00:00',
        ]);
        DB::table('sqa_groups')->insert([
            'id' => 1,
            'name' => 'Grupo GIA',
            'color' => '#22c55e',
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);
        DB::table('sqa_group_users')->insert([
            'sqa_group_id' => 1,
            'user_id' => 10,
            'main' => true,
            'created_at' => '2026-08-01 10:00:00',
            'updated_at' => '2026-08-01 10:00:00',
        ]);
        DB::table('event_comments')->insert([
            [
                'id' => 1,
                'event_id' => 1,
                'user_id' => 10,
                'parent_id' => null,
                'comment' => 'Revisad el material antes del evento.',
                'is_pinned' => true,
                'created_at' => '2026-08-08 18:00:00',
                'updated_at' => '2026-08-08 18:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => 2,
                'event_id' => 1,
                'user_id' => 10,
                'parent_id' => 1,
                'comment' => 'Material revisado y preparado.',
                'is_pinned' => false,
                'created_at' => '2026-08-08 19:00:00',
                'updated_at' => '2026-08-08 19:00:00',
                'deleted_at' => null,
            ],
            [
                'id' => 3,
                'event_id' => 1,
                'user_id' => 10,
                'parent_id' => null,
                'comment' => 'Comentario eliminado.',
                'is_pinned' => false,
                'created_at' => '2026-08-08 20:00:00',
                'updated_at' => '2026-08-08 20:00:00',
                'deleted_at' => '2026-08-08 21:00:00',
            ],
        ]);

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Evento activo')
            ->assertSee('Altis')
            ->assertSee(route('maps.show', 1), escape: false)
            ->assertSee('Briefing visible del operativo.')
            ->assertSee('Mando')
            ->assertDontSee('Red secreta')
            ->assertSee('ACE')
            ->assertSee('US Army')
            ->assertSee('Alpha 1')
            ->assertSee('Alfa Uno')
            ->assertSee('class="event-orbat__occupant-user"', escape: false)
            ->assertSee('--member-group-color: #22c55e', escape: false)
            ->assertSee('aria-label="Secciones del evento"', escape: false)
            ->assertSee('href="#briefing"', escape: false)
            ->assertSee('href="#orbat"', escape: false)
            ->assertSee('href="#comunicaciones"', escape: false)
            ->assertSee('href="#addons"', escape: false)
            ->assertSee('href="#comentarios"', escape: false)
            ->assertSee('id="datos-evento"', escape: false)
            ->assertSee('id="briefing"', escape: false)
            ->assertSee('id="orbat"', escape: false)
            ->assertSee('id="movimientos"', escape: false)
            ->assertSee('id="comunicaciones"', escape: false)
            ->assertSee('id="addons"', escape: false)
            ->assertSee('id="comentarios"', escape: false)
            ->assertSee('Revisad el material antes del evento.')
            ->assertSee('Material revisado y preparado.')
            ->assertSee('Fijado')
            ->assertSee('--member-group-color: #22c55e', escape: false)
            ->assertDontSee('Comentario eliminado.')
            ->assertDontSee('Alpha oculto')
            ->assertDontSee('Grupo secreto')
            ->assertDontSee('Slot secreto')
            ->assertSeeInOrder([
                'Briefing visible del operativo.',
                'ORBAT',
                'Mando',
                'ACE',
                'Revisad el material antes del evento.',
            ]);

        $this->assertStringContainsString(
            ".event-orbat__slots {\n    display: grid;\n    grid-template-columns: 1fr;",
            file_get_contents(public_path('css/events.css')),
        );
        $this->assertStringContainsString(
            ".event-orbat {\n    display: grid;\n    grid-template-columns: repeat(2, minmax(0, 1fr));",
            file_get_contents(public_path('css/events.css')),
        );
        $this->assertStringContainsString(
            'availableWidth / signatureWidth',
            file_get_contents(resource_path('views/firmas/show.blade.php')),
        );
        $this->assertStringNotContainsString(
            '$escalaMovil',
            file_get_contents(resource_path('views/firmas/show.blade.php')),
        );
    }

    public function test_draft_event_page_is_not_public(): void
    {
        $this->get('/eventos/3')->assertNotFound();
    }

    public function test_authenticated_user_can_publish_and_edit_own_event_comments(): void
    {
        DB::table('users')->insert([
            [
                'id' => 20,
                'nick' => 'Comentarista',
                'image' => 'users/comentarista.png',
                'firma' => '/firmas/comentarista.html',
            ],
            [
                'id' => 21,
                'nick' => 'Otro usuario',
                'image' => null,
                'firma' => null,
            ],
        ]);

        $user = User::query()->findOrFail(20);

        $this->actingAs($user)
            ->get('/eventos/1')
            ->assertOk()
            ->assertSee('Publicar comentario')
            ->assertSee(route('events.comments.store', 1), escape: false);

        $this->post('/eventos/1/comentarios', [
            'comment' => 'Comentario recién publicado.',
        ])
            ->assertRedirect('/eventos/1#comentarios')
            ->assertSessionHas('comment_status');

        $commentId = (int) DB::table('event_comments')->where('user_id', 20)->value('id');

        $this->assertDatabaseHas('event_comments', [
            'id' => $commentId,
            'event_id' => 1,
            'user_id' => 20,
            'comment' => 'Comentario recién publicado.',
        ]);

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Comentario recién publicado.')
            ->assertSee('Editar comentario')
            ->assertSee('storage/users/comentarista.png', escape: false)
            ->assertSee('class="event-comment__signature"', escape: false)
            ->assertSee('src="/firmas/comentarista.html"', escape: false)
            ->assertSee('data-signature-frame', escape: false)
            ->assertSee('js/events.js', escape: false)
            ->assertDontSee('style="height:', escape: false)
            ->assertSee(route('events.comments.update', [1, $commentId]), escape: false);

        $this->patch("/eventos/1/comentarios/{$commentId}", [
            'comment' => 'Comentario actualizado.',
        ])
            ->assertRedirect('/eventos/1#comentarios')
            ->assertSessionHas('comment_status');

        $this->assertDatabaseHas('event_comments', [
            'id' => $commentId,
            'comment' => 'Comentario actualizado.',
            'updated_by' => 20,
        ]);

        $this->actingAs(User::query()->findOrFail(21))
            ->post('/eventos/1/comentarios', [
                'parent_id' => $commentId,
                'comment' => 'Respuesta al comentario actualizado.',
            ])
            ->assertRedirect('/eventos/1#comentarios')
            ->assertSessionHas('comment_status', 'Tu respuesta se ha publicado correctamente.');

        $replyId = (int) DB::table('event_comments')
            ->where('parent_id', $commentId)
            ->value('id');

        $this->assertDatabaseHas('event_comments', [
            'id' => $replyId,
            'event_id' => 1,
            'user_id' => 21,
            'parent_id' => $commentId,
            'comment' => 'Respuesta al comentario actualizado.',
        ]);

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Responder')
            ->assertSee('Publicar respuesta')
            ->assertSee('Respuesta al comentario actualizado.')
            ->assertSee('class="event-comment is-reply"', escape: false)
            ->assertSeeInOrder([
                'Comentario actualizado.',
                'Respuesta al comentario actualizado.',
            ]);

        $this->patch("/eventos/1/comentarios/{$commentId}", [
            'comment' => 'Intento de edición ajena.',
        ])
            ->assertForbidden();

        $this->assertDatabaseMissing('event_comments', [
            'id' => $commentId,
            'comment' => 'Intento de edición ajena.',
        ]);
    }

    public function test_user_cannot_reply_to_a_comment_from_another_event(): void
    {
        DB::table('users')->insert(['id' => 23, 'nick' => 'Comentarista']);
        DB::table('event_comments')->insert([
            'id' => 21,
            'event_id' => 2,
            'user_id' => 23,
            'parent_id' => null,
            'comment' => 'Comentario de otro evento.',
            'is_pinned' => false,
            'created_at' => '2026-08-08 18:00:00',
            'updated_at' => '2026-08-08 18:00:00',
        ]);

        $this->actingAs(User::query()->findOrFail(23))
            ->post('/eventos/1/comentarios', [
                'parent_id' => 21,
                'comment' => 'Respuesta cruzada no permitida.',
            ])
            ->assertSessionHasErrors('comment');

        $this->assertDatabaseMissing('event_comments', [
            'event_id' => 1,
            'parent_id' => 21,
        ]);
    }

    public function test_guest_cannot_publish_or_edit_event_comments(): void
    {
        DB::table('users')->insert(['id' => 22, 'nick' => 'Autor']);
        DB::table('event_comments')->insert([
            'id' => 20,
            'event_id' => 1,
            'user_id' => 22,
            'parent_id' => null,
            'comment' => 'Comentario existente.',
            'is_pinned' => false,
            'created_at' => '2026-08-08 18:00:00',
            'updated_at' => '2026-08-08 18:00:00',
        ]);

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Inicia sesión')
            ->assertDontSee('Publicar comentario');

        $this->post('/eventos/1/comentarios', ['comment' => 'No permitido'])
            ->assertRedirect('/login');
        $this->patch('/eventos/1/comentarios/20', ['comment' => 'No permitido'])
            ->assertRedirect('/login');
    }

    public function test_map_page_shows_all_available_map_data(): void
    {
        $this->get('/mapas/1')
            ->assertOk()
            ->assertSee('Altis')
            ->assertSee('Arma 3')
            ->assertSee('Isla mediterránea con amplias zonas urbanas y rurales.')
            ->assertSee('storage/maps/altis.jpg', escape: false)
            ->assertSee('https://example.com/maps/altis', escape: false)
            ->assertSee('target="_blank"', escape: false)
            ->assertSee('rel="noopener noreferrer"', escape: false);
    }

    public function test_eligible_user_can_register_and_move_between_visible_slots(): void
    {
        $this->seedSlotRegistrationConfiguration();
        DB::table('users')->insert([
            ['id' => 11, 'nick' => 'Bravo Uno', 'status_id' => 1],
            ['id' => 12, 'nick' => 'Bravo Dos', 'status_id' => 1],
        ]);

        $user = User::query()->findOrFail(11);

        $this->actingAs($user)
            ->get('/eventos/1')
            ->assertOk()
            ->assertSee('Apuntarme')
            ->assertSee(route('events.slots.register', [1, 'slot-alpha']), escape: false);

        $this->post('/eventos/1/slots/slot-alpha')
            ->assertRedirect('/eventos/1')
            ->assertSessionHas('status');

        $this->assertDatabaseHas('event_slots', [
            'event_id' => 1,
            'slot_key' => 'slot-alpha',
            'user_id' => 11,
            'name' => 'Alpha 1',
            'slot_type_id' => 1,
            'slot_group' => 'Alpha',
            'faction_id' => 1,
        ]);
        $this->assertDatabaseHas('event_slot_history', [
            'event_id' => 1,
            'user_id' => 11,
            'action' => 'assigned',
            'from_slot_key' => null,
            'to_slot_key' => 'slot-alpha',
            'changed_by_user_id' => 11,
        ]);

        $this->post('/eventos/1/slots/slot-medic')
            ->assertRedirect('/eventos/1')
            ->assertSessionHas('status');

        $this->assertDatabaseMissing('event_slots', [
            'event_id' => 1,
            'slot_key' => 'slot-alpha',
            'user_id' => 11,
        ]);
        $this->assertDatabaseHas('event_slots', [
            'event_id' => 1,
            'slot_key' => 'slot-medic',
            'user_id' => 11,
        ]);
        $this->assertDatabaseHas('event_slot_history', [
            'event_id' => 1,
            'user_id' => 11,
            'action' => 'moved',
            'from_slot_key' => 'slot-alpha',
            'to_slot_key' => 'slot-medic',
            'changed_by_user_id' => 11,
        ]);
        $this->assertSame(
            1,
            DB::table('event_slots')->where('event_id', 1)->where('user_id', 11)->count(),
        );

        $this->actingAs(User::query()->findOrFail(12))
            ->post('/eventos/1/slots/slot-medic')
            ->assertSessionHasErrors('slot');

        $this->actingAs($user)
            ->get('/eventos/1')
            ->assertOk()
            ->assertSee('Desapuntarme')
            ->assertSee(route('events.slots.unregister', [1, 'slot-medic']), escape: false);

        $this->delete('/eventos/1/slots/slot-medic')
            ->assertRedirect('/eventos/1')
            ->assertSessionHas('status', 'Te has desapuntado correctamente.');

        $this->assertDatabaseMissing('event_slots', [
            'event_id' => 1,
            'slot_key' => 'slot-medic',
            'user_id' => 11,
        ]);
        $this->assertDatabaseHas('event_slot_history', [
            'event_id' => 1,
            'user_id' => 11,
            'action' => 'unassigned',
            'from_slot_key' => 'slot-medic',
            'to_slot_key' => null,
            'changed_by_user_id' => 11,
        ]);
        $this->assertDatabaseCount('event_slot_history', 3);

        auth()->logout();

        $this->get('/eventos/1')
            ->assertOk()
            ->assertSee('Movimientos de slots')
            ->assertSee('Bravo Uno')
            ->assertSee('se apuntó a')
            ->assertSee('se movió de')
            ->assertSee('se desapuntó de')
            ->assertSeeInOrder(['Alpha · Alpha 1', 'Alpha · Médico']);
    }

    public function test_user_cannot_register_for_disallowed_or_hidden_slot(): void
    {
        $this->seedSlotRegistrationConfiguration();
        DB::table('status')->insert(['id' => 2, 'name' => 'RECLUTA']);
        DB::table('users')->insert(['id' => 13, 'nick' => 'Recluta Uno', 'status_id' => 2]);

        $this->actingAs(User::query()->findOrFail(13))
            ->post('/eventos/1/slots/slot-alpha')
            ->assertSessionHasErrors('slot');

        $this->post('/eventos/1/slots/slot-hidden')
            ->assertSessionHasErrors('slot');

        DB::table('users')->where('id', 13)->update(['status_id' => 1]);
        DB::table('events')->where('id', 1)->update(['event_status_id' => 2]);

        $this->post('/eventos/1/slots/slot-alpha')
            ->assertSessionHasErrors('slot');

        $this->assertDatabaseMissing('event_slots', [
            'event_id' => 1,
            'user_id' => 13,
        ]);
        $this->assertDatabaseCount('event_slot_history', 0);
    }

    private function seedSlotRegistrationConfiguration(): void
    {
        DB::table('status')->insert(['id' => 1, 'name' => 'MIEMBRO']);
        DB::table('slot_types')->insert([
            ['id' => 1, 'name' => 'Líder'],
            ['id' => 2, 'name' => 'Médico'],
        ]);
        DB::table('slot_types_status')->insert([
            ['slot_type_id' => 1, 'status_id' => 1],
            ['slot_type_id' => 2, 'status_id' => 1],
        ]);
        DB::table('armies')->insert(['id' => 1, 'name' => 'OTAN']);
        DB::table('sides')->insert(['id' => 1, 'name' => 'BLUFOR']);
        DB::table('factions')->insert([
            'id' => 1,
            'army_id' => 1,
            'side_id' => 1,
            'name' => 'US Army',
        ]);
        DB::table('events')->where('id', 1)->update([
            'orbat' => json_encode([
                'groups' => [[
                    'name' => 'Alpha',
                    'faction_id' => 1,
                    'visible' => true,
                    'slots' => [
                        ['slot_key' => 'slot-alpha', 'name' => 'Alpha 1', 'slot_type_id' => 1, 'visible' => true],
                        ['slot_key' => 'slot-medic', 'name' => 'Médico', 'slot_type_id' => 2, 'visible' => true],
                        ['slot_key' => 'slot-hidden', 'name' => 'Slot oculto', 'slot_type_id' => 1, 'visible' => false],
                    ],
                ]],
            ]),
        ]);
    }
}
