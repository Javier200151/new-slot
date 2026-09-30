<?php

namespace Tests\Support;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

trait CreatesEventRegressionSchema
{
    protected function createEventRegressionSchema(): void
    {
        config(['activitylog.enabled' => false]);

        Schema::create('status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('color')->nullable();
            $table->boolean('is_system')->default(true);
            $table->softDeletes();
        });

        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('nick');
            $table->string('email')->nullable();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('password')->nullable();
            $table->foreignId('status_id')->nullable();
            $table->string('image')->nullable();
            $table->string('firma')->nullable();
            $table->rememberToken();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('event_status', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
        });

        Schema::create('activities', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('activity_id')->nullable();
            $table->foreignId('event_status_id');
            $table->string('name');
            $table->dateTime('date')->nullable();
            $table->dateTime('end_date')->nullable();
            $table->unsignedInteger('duration')->nullable();
            $table->json('orbat')->nullable();
            $table->boolean('reservations_enabled')->default(false);
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('slot_types', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('image')->nullable();
        });

        Schema::create('slot_types_status', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('slot_type_id');
            $table->foreignId('status_id');
        });

        Schema::create('factions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('army_id')->nullable();
            $table->foreignId('side_id')->nullable();
            $table->string('name');
            $table->timestamps();
        });

        Schema::create('allies', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
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
    }

    protected function seedBasicEventRegressionData(): void
    {
        DB::table('status')->insert([
            ['id' => 1, 'name' => 'ACTIVO', 'color' => '#4ade80', 'is_system' => true],
            ['id' => 2, 'name' => 'RECLUTA', 'color' => '#facc15', 'is_system' => true],
            ['id' => 3, 'name' => 'RESERVA', 'color' => '#60a5fa', 'is_system' => true],
        ]);

        DB::table('event_status')->insert([
            ['id' => 1, 'name' => 'ACTIVO'],
            ['id' => 2, 'name' => 'FINALIZADO'],
        ]);

        DB::table('activities')->insert([
            'id' => 1,
            'name' => 'Operación Regression',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('slot_types')->insert([
            'id' => 1,
            'name' => 'Fusilero',
            'image' => null,
        ]);

        DB::table('slot_types_status')->insert([
            ['slot_type_id' => 1, 'status_id' => 1],
            ['slot_type_id' => 1, 'status_id' => 2],
            ['slot_type_id' => 1, 'status_id' => 3],
        ]);

        DB::table('factions')->insert([
            'id' => 1,
            'army_id' => 1,
            'side_id' => null,
            'name' => 'BLUFOR',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function insertVerifiedUser(int $id, string $nick, int $statusId = 1): void
    {
        DB::table('users')->insert([
            'id' => $id,
            'nick' => $nick,
            'email' => strtolower(str_replace(' ', '.', $nick)) . '@example.test',
            'email_verified_at' => now(),
            'password' => bcrypt('password'),
            'status_id' => $statusId,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    protected function insertActiveEvent(int $id = 1, bool $reservationsEnabled = true): void
    {
        DB::table('events')->insert([
            'id' => $id,
            'activity_id' => 1,
            'event_status_id' => 1,
            'name' => 'Evento Regression ' . $id,
            'date' => now()->addDay(),
            'end_date' => now()->addDay()->addHours(2),
            'duration' => 120,
            'reservations_enabled' => $reservationsEnabled,
            'orbat' => json_encode([
                'groups' => [[
                    'name' => 'Alpha',
                    'visible' => true,
                    'faction_id' => 1,
                    'slots' => [[
                        'slot_key' => 'slot-alpha-1',
                        'name' => 'Fusilero 1',
                        'slot_type_id' => 1,
                        'visible' => true,
                    ]],
                ]],
            ]),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }
}
