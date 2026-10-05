<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Middleware\EnsureEmailIsVerified;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class PublicEventsPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // These tests target event-page behaviour, not the email-verification middleware.
        // Test users are intentionally minimal, so bypass only that middleware here.
        $this->withoutMiddleware(EnsureEmailIsVerified::class);

        Schema::create('event_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('event_results', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('activity_statuses', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->string('description')->nullable();
        });

        Schema::create('activity_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->boolean('oficial')->default(false);
            $table->string('color')->nullable();
            $table->boolean('uses_enemy_factions')->default(false);
            $table->boolean('uses_event_result')->default(false);
            $table->boolean('supports_ocap')->default(false);
            $table->boolean('supports_respawn')->default(false);
            $table->boolean('supports_jip')->default(false);
            $table->boolean('awards_metopa')->default(false);
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

        Schema::create('activity_days', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('activity_day_assignments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id');
            $table->foreignId('activity_day_id');
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->foreignId('promo_id')->nullable();
            $table->foreignId('tutor_id')->nullable();
            $table->string('firma')->nullable();
            $table->string('image')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('metopas', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('image');
            $table->foreignId('sqa_group_id')->nullable();
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

        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
        });

        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id');
            $table->foreignId('role_id');
        });

        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->foreignId('permission_id');
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
            $table->boolean('coordinator')->default(false);
            $table->foreignId('updated_by')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('allies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
            $table->string('url')->nullable();
        });

        Schema::create('countries', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
        });

        Schema::create('armies', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('country_id')->nullable();
            $table->string('name');
            $table->string('description')->nullable();
            $table->string('image')->nullable();
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
            $table->string('image')->nullable();
        });

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_system')->default(true);
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
            $table->boolean('persistent')->default(false);
            $table->text('description')->nullable();
            $table->foreignId('editor_id')->nullable();
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_type_id');
            $table->foreignId('activity_status_id')->nullable();
            $table->foreignId('period_id')->nullable();
            $table->foreignId('platform_id')->nullable();
            $table->foreignId('map_id')->nullable();
            $table->foreignId('campaign_id')->nullable();
            $table->foreignId('editor_id')->nullable();
            $table->foreignId('editor_ally_id')->nullable();
            $table->foreignId('metopa_id')->nullable();
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

        Schema::create('activity_enemy_faction', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('faction_id');
            $table->foreignId('activity_id');
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id');
            $table->foreignId('event_status_id');
            $table->foreignId('event_result_id')->nullable();
            $table->string('name');
            $table->dateTime('date');
            $table->dateTime('end_date')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->json('orbat')->nullable();
            $table->string('ocap_url')->nullable();
            $table->boolean('multiclans')->default(false);
            $table->boolean('reservations_enabled')->default(false);
            $table->foreignId('created_by')->nullable();
            $table->foreignId('updated_by')->nullable();
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

        Schema::create('event_reservations', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('user_id');
            $table->foreignId('created_by')->nullable();
            $table->timestamps();
            $table->unique(['event_id', 'user_id']);
        });

        Schema::create('recruitment_periods', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('open_user_id')->nullable()->unique();
            $table->boolean('official_events_allowed')->default(true);
            $table->timestamps();
        });

        Schema::create('recruitment_reentry_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id');
            $table->foreignId('pending_user_id')->nullable()->unique();
            $table->foreignId('previous_period_id')->nullable();
            $table->string('review_type', 40)->default('PROMOTED_TO_RECRUIT');
            $table->foreignId('tutorial_tutor_user_id')->nullable();
            $table->timestamp('tutorial_approved_at')->nullable();
            $table->timestamp('detected_at')->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 40)->nullable();
            $table->foreignId('resolved_by_user_id')->nullable();
            $table->text('resolution_note')->nullable();
            $table->timestamps();
        });

        Schema::create('community_roulette_rooms', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id');
            $table->string('target_slot_key')->nullable();
            $table->string('target_slot_name')->nullable();
            $table->foreignId('target_slot_type_id')->nullable();
            $table->string('target_slot_group')->nullable();
            $table->foreignId('target_faction_id')->nullable();
            $table->foreignId('created_by')->nullable();
            $table->string('status', 24)->default('active');
            $table->unsignedTinyInteger('active_key')->nullable();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('spin_started_at')->nullable();
            $table->timestamp('spin_ends_at')->nullable();
            $table->unsignedInteger('spin_duration_ms')->nullable();
            $table->unsignedInteger('winning_ticket_index')->nullable();
            $table->decimal('final_rotation', 10, 3)->nullable();
            $table->foreignId('winner_user_id')->nullable();
            $table->boolean('winner_was_viewing')->default(false);
            $table->foreignId('winner_phrase_id')->nullable();
            $table->string('winner_phrase_text', 500)->nullable();
            $table->string('failure_reason', 500)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('streamers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable();
            $table->boolean('enable')->default(true);
            $table->string('twitch_channel')->nullable();
            $table->string('twitch_user_id')->nullable();
            $table->string('youtube_channel')->nullable();
            $table->string('youtube_channel_id')->nullable();
            $table->string('other_channel')->nullable();
            $table->string('website_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('streams', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id')->nullable();
            $table->foreignId('streamer_id');
            $table->string('platform')->nullable();
            $table->string('stream_url')->nullable();
            $table->boolean('enabled')->default(true);
            $table->string('title')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('ended_at')->nullable();
            $table->timestamps();
        });

        Schema::create('event_media', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('event_id');
            $table->foreignId('user_id')->nullable();
            $table->string('type');
            $table->string('provider');
            $table->string('url');
            $table->string('external_id')->nullable();
            $table->string('title');
            $table->timestamps();
        });

        Schema::create('campaign_aars', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('campaign_id');
            $table->foreignId('event_id')->unique();
            $table->foreignId('commander_user_id')->nullable();
            $table->string('status', 20)->default('pending');
            $table->json('sections')->nullable();
            $table->json('orbat_snapshot')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->foreignId('updated_by')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamps();
        });

        Schema::create('notifications', function (Blueprint $table): void {
            $table->uuid('id')->primary();
            $table->string('type');
            $table->morphs('notifiable');
            $table->text('data');
            $table->timestamp('read_at')->nullable();
            $table->timestamps();
        });

        DB::table('event_status')->insert([
            ['id' => 1, 'name' => 'ACTIVO'],
            ['id' => 2, 'name' => 'FINALIZADO'],
            ['id' => 3, 'name' => 'BORRADOR'],
        ]);

        DB::table('event_results')->insert([
            ['id' => 1, 'name' => 'ÉXITO'],
        ]);

        DB::table('activity_statuses')->insert([
            ['id' => 1, 'name' => 'ACTIVO', 'color' => '#22c55e'],
        ]);

        DB::table('activity_types')->insert([
            ['id' => 1, 'name' => 'Oficial', 'oficial' => true, 'color' => '#f59e0b', 'uses_event_result' => false],
            ['id' => 2, 'name' => 'Prácticas', 'oficial' => false, 'color' => '#22c55e', 'uses_event_result' => true],
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

        DB::table('activities')->insert([
            [
                'id' => 1,
                'activity_type_id' => 1,
                'activity_status_id' => 1,
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
                'activity_type_id' => 2,
                'activity_status_id' => 1,
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
                'activity_type_id' => 1,
                'activity_status_id' => 1,
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
                'activity_id' => 1,
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
                'activity_id' => 2,
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
                'activity_id' => 1,
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
            ['event_id' => 1, 'slot_key' => 'slot-1', 'user_id' => 10, 'ally_id' => null],
            ['event_id' => 1, 'slot_key' => 'slot-2', 'user_id' => null, 'ally_id' => 20],
            ['event_id' => 1, 'slot_key' => 'slot-3', 'user_id' => null, 'ally_id' => null],
        ]);
    }

    public function test_calendar_lists_active_finished_and_read_only_draft_events_for_the_selected_month(): void
    {
        $response = $this->get('/eventos?month=8&year=2026');

        $response
            ->assertOk()
            ->assertSee('Agosto 2026')
            ->assertSee('Evento activo')
            ->assertSee('Evento finalizado')
            ->assertSee('BORRADOR')
            ->assertSee('Evento borrador')
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
            ->assertSeeInOrder(['id="evento-3"', 'id="evento-2"', 'id="evento-1"'], escape: false);
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
            'activity_id' => 1,
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
            ->assertSee('3 eventos encontrados')
            ->assertSeeInOrder(['id="evento-4"', 'id="evento-3"', 'id="evento-2"'], escape: false);
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
        DB::table('activity_days')->insert(['id' => 1, 'name' => 'Viernes']);
        DB::table('activity_day_assignments')->insert(['activity_id' => 1, 'activity_day_id' => 1]);
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
        DB::table('activity_enemy_faction')->insert([
            'faction_id' => 1,
            'activity_id' => 1,
        ]);

        DB::table('activities')->where('id', 1)->update([
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
            'availableWidth',
            file_get_contents(resource_path('views/firmas/show.blade.php')),
        );
        $this->assertStringContainsString(
            '/ baseWidth',
            file_get_contents(resource_path('views/firmas/show.blade.php')),
        );
        $this->assertStringContainsString(
            '$escalaMovil = 0.55;',
            file_get_contents(resource_path('views/firmas/show.blade.php')),
        );
    }

    public function test_draft_event_page_is_public_but_strictly_read_only(): void
    {
        DB::table('users')->insert(['id' => 24, 'nick' => 'Autor del borrador']);

        $response = $this->actingAs(User::query()->findOrFail(24))
            ->get('/eventos/3');

        $response
            ->assertOk()
            ->assertSee('Vista previa en solo lectura')
            ->assertSee('Los comentarios están desactivados mientras el evento permanezca en borrador.')
            ->assertDontSee('Publicar comentario')
            ->assertDontSee('Apuntarme')
            ->assertDontSee('Modo edición')
            ->assertDontSee('data-event-live', escape: false);

        $this->post('/eventos/3/comentarios', [
            'comment' => 'No debe publicarse.',
        ])->assertNotFound();

        $this->assertDatabaseMissing('event_comments', [
            'event_id' => 3,
            'comment' => 'No debe publicarse.',
        ]);
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

    public function test_author_can_delete_comment_and_nested_replies_are_soft_deleted(): void
    {
        DB::table('users')->insert([
            ['id' => 30, 'nick' => 'Autor'],
            ['id' => 31, 'nick' => 'Respuesta'],
        ]);

        DB::table('event_comments')->insert([
            [
                'id' => 30,
                'event_id' => 1,
                'user_id' => 30,
                'parent_id' => null,
                'comment' => 'Comentario padre.',
                'is_pinned' => false,
                'created_at' => '2026-08-08 18:00:00',
                'updated_at' => '2026-08-08 18:00:00',
            ],
            [
                'id' => 31,
                'event_id' => 1,
                'user_id' => 31,
                'parent_id' => 30,
                'comment' => 'Respuesta hija.',
                'is_pinned' => false,
                'created_at' => '2026-08-08 18:01:00',
                'updated_at' => '2026-08-08 18:01:00',
            ],
            [
                'id' => 32,
                'event_id' => 1,
                'user_id' => 30,
                'parent_id' => 31,
                'comment' => 'Respuesta nieta.',
                'is_pinned' => false,
                'created_at' => '2026-08-08 18:02:00',
                'updated_at' => '2026-08-08 18:02:00',
            ],
        ]);

        $author = User::query()->findOrFail(30);

        $this->actingAs($author)
            ->get('/eventos/1')
            ->assertOk()
            ->assertSee(route('events.comments.destroy', [1, 30]), escape: false)
            ->assertSee('aria-label="Eliminar comentario"', escape: false);

        $this->delete('/eventos/1/comentarios/30')
            ->assertRedirect('/eventos/1#comentarios')
            ->assertSessionHas(
                'comment_status',
                'El comentario y sus respuestas se han eliminado correctamente.'
            );

        foreach ([30, 31, 32] as $commentId) {
            $this->assertNotNull(
                DB::table('event_comments')->where('id', $commentId)->value('deleted_at')
            );
        }
    }

    public function test_user_cannot_delete_another_users_event_comment_without_permission(): void
    {
        DB::table('users')->insert([
            ['id' => 33, 'nick' => 'Autor protegido'],
            ['id' => 34, 'nick' => 'Otro usuario'],
        ]);

        DB::table('event_comments')->insert([
            'id' => 33,
            'event_id' => 1,
            'user_id' => 33,
            'parent_id' => null,
            'comment' => 'No se puede borrar por otro usuario.',
            'is_pinned' => false,
            'created_at' => '2026-08-08 18:00:00',
            'updated_at' => '2026-08-08 18:00:00',
        ]);

        $this->actingAs(User::query()->findOrFail(34))
            ->delete('/eventos/1/comentarios/33')
            ->assertForbidden();

        $this->assertNull(
            DB::table('event_comments')->where('id', 33)->value('deleted_at')
        );
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
            ->assertSessionHasErrors('parent_id');

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
        $this->delete('/eventos/1/comentarios/20')
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
            ->assertRedirect('/eventos/1#orbat')
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
            ->assertRedirect('/eventos/1#orbat')
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
            ->assertRedirect('/eventos/1#orbat')
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
