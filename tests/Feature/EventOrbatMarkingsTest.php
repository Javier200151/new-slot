<?php

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use App\Services\EventOrbatMarkingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\PermissionRegistrar;
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

    /** Build only the standard Spatie tables needed to exercise real role checks. */
    private function createOrbatPermissionTestTables(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('roles', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('guard_name');
            $table->timestamps();
            $table->unique(['name', 'guard_name']);
        });
        Schema::create('role_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->unsignedBigInteger('role_id');
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('model_has_roles', function (Blueprint $table): void {
            $table->unsignedBigInteger('role_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['role_id', 'model_id', 'model_type']);
        });
        Schema::create('model_has_permissions', function (Blueprint $table): void {
            $table->unsignedBigInteger('permission_id');
            $table->string('model_type');
            $table->unsignedBigInteger('model_id');
            $table->primary(['permission_id', 'model_id', 'model_type']);
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_role_permission_can_edit_all_squads_without_being_registered(): void
    {
        $this->createOrbatPermissionTestTables();
        $this->insertVerifiedUser(20, 'Organizador');

        $permission = \App\Models\Permission::firstOrCreate([
            'name' => 'orbat-markings.edit', 'guard_name' => 'web',
        ]);
        $role = \App\Models\Role::firstOrCreate([
            'name' => 'organizador', 'guard_name' => 'web',
        ]);
        $role->givePermissionTo($permission);
        $editor = User::findOrFail(20);
        $editor->assignRole($role);

        $this->assertDatabaseMissing('event_slots', ['event_id' => 1, 'user_id' => 20]);
        $this->assertTrue(app(EventOrbatMarkingService::class)
            ->permission(Event::findOrFail(1)->load('eventStatus'), $editor, 'Alpha'));
        $this->assertTrue(app(EventOrbatMarkingService::class)
            ->permission(Event::findOrFail(1)->load('eventStatus'), $editor, 'Bravo'));

        $this->actingAs($editor)
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Alpha', 'items' => $this->alphaItems(),
            ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('event_orbat_markings', [
            'event_id' => 1, 'participant_key' => 'user:10', 'number' => 1,
        ]);
        $this->assertDatabaseHas('event_orbat_marking_notices', [
            'event_id' => 1, 'actor_id' => 20, 'user_id' => 10,
        ]);

        $role->revokePermissionTo($permission);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $editor = User::findOrFail(20);
        $this->actingAs($editor)
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Bravo', 'items' => [
                    ['slot_key' => 'slot-bravo-1', 'number' => 1, 'color' => 'blue'],
                ],
            ])->assertForbidden();
    }

    public function test_orbat_role_permission_exists_in_role_catalog(): void
    {
        $options = \App\Support\PermissionCatalog::actionOptionsFor('orbat-markings');
        $this->assertSame('Editar numeraciones y colores', $options['edit'] ?? null);
        $this->assertContains('orbat-markings.edit', \App\Support\PermissionCatalog::permissionNames());
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
            ])->assertForbidden();

        DB::table('slot_types')->where('id', 1)->update(['orbat_marking_permission' => 'none']);
        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha', 'items' => $this->alphaItems(),
        ])->assertForbidden();
        $this->assertDatabaseCount('event_orbat_markings', 0);
    }

    public function test_invalid_colors_duplicate_numbers_and_vacant_slots_are_rejected(): void
    {
        $user = User::findOrFail(10);
        $duplicate = $this->alphaItems();
        $duplicate[1]['number'] = 1;
        $duplicate[1]['color'] = 'green';
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

    public function test_same_number_is_allowed_for_players_of_different_colors(): void
    {
        $items = $this->alphaItems();
        $items[1]['number'] = 1; // Same number, but yellow instead of green.

        $this->actingAs(User::findOrFail(10))
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Alpha', 'items' => $items,
            ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('event_orbat_markings', [
            'participant_key' => 'user:10', 'color' => 'green', 'number' => 1,
        ]);
        $this->assertDatabaseHas('event_orbat_markings', [
            'participant_key' => 'user:11', 'color' => 'yellow', 'number' => 1,
        ]);
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
    public function test_squad_leader_can_assign_and_clear_second_in_command_with_editor_metadata(): void
    {
        $user = User::findOrFail(10);
        $service = app(EventOrbatMarkingService::class);

        $this->actingAs($user)
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Alpha',
                'items' => $this->alphaItems(),
                'second_in_command' => 'slot-alpha-2',
            ])->assertOk()
            ->assertJsonPath('updated_by', 'Lider');

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'second_in_command_key' => 'user:11',
            'updated_by' => 10,
        ]);
        $this->assertSame(
            'slot-alpha-2',
            $service->groupSettings(Event::findOrFail(1))['Alpha']['second_in_command_key'],
        );
        $this->assertDatabaseHas('event_orbat_group_command_history', [
            'previous_second_key' => null,
            'new_second_key' => 'user:11',
            'actor_id' => 10,
        ]);

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'second_in_command' => 'slot-alpha-2',
        ])->assertOk()->assertJsonPath('changed', false);

        $this->assertDatabaseCount('event_orbat_group_command_history', 1);
        $this->assertDatabaseCount('event_orbat_marking_notices', 1);

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'second_in_command' => null,
        ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'second_in_command_key' => null,
        ]);
        $this->assertDatabaseCount('event_orbat_group_command_history', 2);
        $this->assertDatabaseCount('event_orbat_marking_notices', 2);
    }

    public function test_second_in_command_requires_current_occupied_slot_in_the_squad(): void
    {
        $this->actingAs(User::findOrFail(10))->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'second_in_command' => 'slot-alpha-3',
        ])->assertUnprocessable()->assertJsonValidationErrors('second_in_command');

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'second_in_command' => 'slot-bravo-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('second_in_command');

        $this->assertDatabaseCount('event_orbat_group_settings', 0);
        $this->assertDatabaseCount('event_orbat_marking_notices', 0);
    }

    public function test_second_in_command_only_change_is_published_even_without_colors_or_numbers(): void
    {
        $items = [
            ['slot_key' => 'slot-alpha-1', 'number' => null, 'color' => 'white'],
            ['slot_key' => 'slot-alpha-2', 'number' => null, 'color' => 'white'],
            ['slot_key' => 'slot-alpha-3', 'number' => null, 'color' => 'white'],
        ];
        $this->actingAs(User::findOrFail(10))
            ->putJson(route('events.orbat.markings.update', 1), [
                'group' => 'Alpha',
                'items' => $items,
                'second_in_command' => 'slot-alpha-2',
            ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseCount('event_orbat_markings', 0);
        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'second_in_command_key' => 'user:11',
            'updated_by' => 10,
        ]);
        $this->assertDatabaseHas('event_orbat_marking_notices', ['user_id' => 11]);
        $this->assertDatabaseCount('event_orbat_group_command_history', 1);
    }

    public function test_second_in_command_follows_member_after_moving_within_the_same_squad(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $actor = User::findOrFail(10);
        $service->save(Event::findOrFail(1), $actor, 'Alpha', $this->alphaItems(), 'slot-alpha-2');

        // Vacate a target slot before moving the currently designated member.
        DB::table('event_slots')->where('slot_key', 'slot-alpha-2')->update(['slot_key' => 'slot-alpha-3']);

        $this->assertSame(
            'slot-alpha-3',
            $service->groupSettings(Event::findOrFail(1))['Alpha']['second_in_command_key'],
        );
    }

    public function test_legacy_tactical_title_is_preserved_when_new_editor_omits_it(): void
    {
        $actor = User::findOrFail(10);
        $this->actingAs($actor)->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'custom_title' => 'Apoyo · QRF',
            'items' => $this->alphaItems(),
        ])->assertOk()->assertJsonPath('changed', true);

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'custom_title' => 'Apoyo · QRF',
            'updated_by' => 10,
        ]);

        $this->assertSame('Apoyo · QRF', app(EventOrbatMarkingService::class)
            ->groupSettings(Event::findOrFail(1))['Alpha']['custom_title']);

        // The legacy title has no editor anymore. An empty/omitted value
        // must leave it untouched, without a spurious notification.
        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'custom_title' => '',
            'items' => $this->alphaItems(),
        ])->assertOk()->assertJsonPath('changed', false);

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1, 'group_name' => 'Alpha',
            'custom_title' => 'Apoyo · QRF',
        ]);
        $this->assertDatabaseCount('event_orbat_marking_notices', 1);
    }

    public function test_color_team_titles_replace_visible_squad_title_without_erasing_legacy_data(): void
    {
        $actor = User::findOrFail(10);
        $this->actingAs($actor)->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'custom_title' => 'Antiguo título',
            'items' => $this->alphaItems(),
        ])->assertOk();

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'color_team_titles' => [
                'green' => 'Asalto',
                'yellow' => 'Apoyo',
            ],
        ])->assertOk();

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'custom_title' => 'Antiguo título',
        ]);

        $pdf = $this->get(route('events.orbat.markings.pdf', 1))->assertOk()->getContent();
        // Raw PDF content streams escape parentheses in text operators.
        $this->assertStringContainsString('Verde \\(1\\): Asalto', $pdf);
        $this->assertStringContainsString('Amarillo \\(1\\): Apoyo', $pdf);
        $this->assertStringNotContainsString('Antiguo título', $pdf);
    }

    public function test_departing_member_leaves_numbered_white_vacancy_without_renumbering(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $leader = User::findOrFail(10);
        $service->save(Event::findOrFail(1), $leader, 'Alpha', $this->alphaItems(), null, 'Apoyo');

        $this->actingAs(User::findOrFail(11))
            ->delete(route('events.slots.unregister', [1, 'slot-alpha-2']), [
                'reason' => 'No puedo asistir.',
                'reset_tactical_markings' => 0,
            ])->assertRedirect();

        $this->assertDatabaseMissing('event_slots', ['event_id' => 1, 'user_id' => 11]);
        $this->assertDatabaseHas('event_orbat_markings', [
            'event_id' => 1,
            'participant_key' => 'vacant:slot-alpha-2',
            'number' => 2,
            'color' => 'white',
        ]);
        $marks = $service->markings(Event::findOrFail(1));
        $this->assertSame(['number' => 1, 'color' => 'green'], $marks['slot-alpha-1']);
        $this->assertSame(['number' => 2, 'color' => 'white'], $marks['slot-alpha-2']);
        $this->assertSame('Apoyo', $service->groupSettings(Event::findOrFail(1))['Alpha']['custom_title']);

        // The leader can still save the squad without renumbering the vacancy.
        $this->actingAs($leader)->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'custom_title' => 'Apoyo',
            'items' => [
                ['slot_key' => 'slot-alpha-1', 'number' => 1, 'color' => 'green'],
                ['slot_key' => 'slot-alpha-2', 'number' => 2, 'color' => 'white'],
                ['slot_key' => 'slot-alpha-3', 'number' => null, 'color' => 'white'],
            ],
        ])->assertOk()->assertJsonPath('changed', false);
    }

    public function test_departing_last_editor_can_choose_to_clear_every_squad_they_last_edited(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $leader = User::findOrFail(10);
        $service->save(Event::findOrFail(1), $leader, 'Alpha', $this->alphaItems(), 'slot-alpha-2', 'Asalto');

        DB::table('slot_types')->where('id', 1)->update(['orbat_marking_permission' => 'all']);
        $service->save(Event::findOrFail(1), $leader, 'Bravo', [
            ['slot_key' => 'slot-bravo-1', 'number' => 1, 'color' => 'blue'],
        ], null, 'QRF');

        $choices = $service->departureResetChoices(Event::findOrFail(1), $leader);
        $this->assertEqualsCanonicalizing(['Alpha', 'Bravo'], array_column($choices, 'group'));

        $this->actingAs($leader)
            ->delete(route('events.slots.unregister', [1, 'slot-alpha-1']), [
                'reason' => 'No puedo participar.',
                'reset_tactical_markings' => 1,
            ])->assertRedirect();

        $this->assertDatabaseCount('event_orbat_markings', 0);
        $this->assertDatabaseCount('event_orbat_group_settings', 0);
        $this->assertDatabaseHas('event_orbat_marking_history', [
            'event_id' => 1, 'group_name' => 'Bravo',
            'old_color' => 'blue', 'new_color' => 'white',
        ]);
        $this->assertDatabaseHas('event_orbat_group_command_history', [
            'group_name' => 'Alpha', 'previous_title' => 'Asalto', 'new_title' => null,
        ]);
        $this->assertDatabaseHas('event_orbat_marking_notices', ['user_id' => 11]);
        $this->assertDatabaseHas('event_orbat_marking_notices', ['user_id' => 12]);
    }

    public function test_departing_last_editor_can_keep_tactical_configuration(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $leader = User::findOrFail(10);
        $service->save(Event::findOrFail(1), $leader, 'Alpha', $this->alphaItems(), null, 'QRF');

        $this->actingAs($leader)
            ->delete(route('events.slots.unregister', [1, 'slot-alpha-1']), [
                'reason' => 'No puedo participar.',
                'reset_tactical_markings' => 0,
            ])->assertRedirect();

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'group_name' => 'Alpha', 'custom_title' => 'QRF', 'updated_by' => 10,
        ]);
        $this->assertDatabaseHas('event_orbat_markings', [
            'participant_key' => 'vacant:slot-alpha-1', 'number' => 1,
            'color' => 'white',
        ]);
        $this->assertDatabaseHas('event_orbat_markings', [
            'participant_key' => 'user:11', 'number' => 2, 'color' => 'yellow',
        ]);
    }

    public function test_single_member_color_fireteams_can_have_independent_custom_titles(): void
    {
        $this->actingAs(User::findOrFail(10))->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'custom_title' => 'Apoyo',
            'color_team_titles' => [
                'green' => 'Equipo de asalto',
                'yellow' => 'Equipo de seguridad',
            ],
        ])->assertOk()->assertJsonPath('changed', true);

        $settings = app(EventOrbatMarkingService::class)->groupSettings(Event::findOrFail(1));
        $this->assertSame('Equipo de asalto', $settings['Alpha']['color_team_titles']['green']);
        $this->assertSame('Equipo de seguridad', $settings['Alpha']['color_team_titles']['yellow']);

        $this->assertDatabaseHas('event_orbat_group_settings', [
            'event_id' => 1,
            'group_name' => 'Alpha',
            'color_team_titles' => json_encode(['green' => 'Equipo de asalto', 'yellow' => 'Equipo de seguridad'], JSON_UNESCAPED_UNICODE),
        ]);
        $this->assertDatabaseHas('event_orbat_group_command_history', [
            'group_name' => 'Alpha',
            'new_color_team_titles' => json_encode(['green' => 'Equipo de asalto', 'yellow' => 'Equipo de seguridad'], JSON_UNESCAPED_UNICODE),
        ]);

        $this->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'custom_title' => 'Apoyo',
            'color_team_titles' => [
                'green' => 'Equipo de asalto',
                'yellow' => 'Equipo de seguridad',
            ],
        ])->assertOk()->assertJsonPath('changed', false);

        $this->assertDatabaseCount('event_orbat_marking_notices', 1);
    }

    public function test_fireteam_title_for_unused_color_is_rejected(): void
    {
        $this->actingAs(User::findOrFail(10))->putJson(route('events.orbat.markings.update', 1), [
            'group' => 'Alpha',
            'items' => $this->alphaItems(),
            'color_team_titles' => ['blue' => 'No hay jugadores azules'],
        ])->assertUnprocessable()->assertJsonValidationErrors('color_team_titles');

        $this->assertDatabaseCount('event_orbat_markings', 0);
        $this->assertDatabaseCount('event_orbat_marking_notices', 0);
    }

    public function test_tactical_pdf_download_has_public_members_numbers_and_individual_fireteams(): void
    {
        $service = app(EventOrbatMarkingService::class);
        $service->save(Event::findOrFail(1), User::findOrFail(10), 'Alpha',
            $this->alphaItems(), 'slot-alpha-2', 'Apoyo',
            ['green' => 'Asalto', 'yellow' => 'Seguridad']);

        $response = $this->get(route('events.orbat.markings.pdf', 1))->assertOk();
        $this->assertSame('application/pdf', $response->headers->get('Content-Type'));
        $this->assertStringContainsString('attachment;', (string) $response->headers->get('Content-Disposition'));
        $binary = $response->getContent();
        $this->assertStringStartsWith('%PDF-1.4', $binary);
        // The PDF uses color names and optional team titles, not "Equipo".
        $this->assertStringContainsString('Verde \\(1\\): Asalto', $binary);
        $this->assertStringContainsString('Amarillo \\(1\\): Seguridad', $binary);
        $this->assertStringContainsString('Lider', $binary);
        $this->assertStringContainsString('2.-', $binary);
        $this->assertStringContainsString('%%EOF', $binary);
    }

}
