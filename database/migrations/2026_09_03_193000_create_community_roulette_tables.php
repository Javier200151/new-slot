<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('community_roulette_phrases')) {
            Schema::create('community_roulette_phrases', function (Blueprint $table): void {
                $table->id();
                $table->string('text', 500);
                $table->boolean('active')->default(true)->index();
                $table->unsignedSmallInteger('sort_order')->default(100);
                $table->timestamps();
            });

            DB::table('community_roulette_phrases')->insert([
                [
                    'text' => 'La ruleta ha hablado. Nuestras condolencias.',
                    'active' => true,
                    'sort_order' => 10,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'Responsabilidad desbloqueada. Era mejor no ganar.',
                    'active' => true,
                    'sort_order' => 20,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'Has sido ascendido oficialmente a problema de los demás.',
                    'active' => true,
                    'sort_order' => 30,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'El azar te considera cualificado. Nosotros no hemos sido consultados.',
                    'active' => true,
                    'sort_order' => 40,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'Felicidades. Ahora todo el mundo espera que sepas qué estás haciendo.',
                    'active' => true,
                    'sort_order' => 50,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'Premio desbloqueado: más radio, menos paz.',
                    'active' => true,
                    'sort_order' => 60,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
                [
                    'text' => 'Enhorabuena. Desde este momento, cualquier problema táctico lleva tu nombre.',
                    'active' => true,
                    'sort_order' => 70,
                    'created_at' => now(),
                    'updated_at' => now(),
                ],
            ]);
        }

        if (! Schema::hasTable('community_roulette_rooms')) {
            Schema::create('community_roulette_rooms', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')
                    ->constrained('events')
                    ->restrictOnDelete();
                $table->char('target_slot_key', 26);
                $table->string('target_slot_name');
                // El histórico conserva el nombre del slot. Si en el futuro
                // se elimina el tipo del catálogo, no bloqueamos esa limpieza.
                $table->foreignId('target_slot_type_id')
                    ->nullable()
                    ->constrained('slot_types')
                    ->nullOnDelete();
                $table->string('target_slot_group');
                // La facción se usa para validar la sala mientras está activa;
                // una eliminación futura del catálogo no debe romper el histórico.
                $table->foreignId('target_faction_id')
                    ->nullable()
                    ->constrained('factions')
                    ->nullOnDelete();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('status', 24)->default('active')->index();

                // MySQL permite múltiples NULL en un UNIQUE. Mientras la sala
                // bloquea inscripciones vale 1; al finalizar/cerrar vuelve a NULL.
                $table->unsignedTinyInteger('active_key')->nullable()->unique();

                $table->timestamp('expires_at')->index();
                $table->timestamp('spin_started_at', 3)->nullable();
                $table->timestamp('spin_ends_at', 3)->nullable();
                $table->unsignedInteger('spin_duration_ms')->nullable();
                $table->unsignedInteger('winning_ticket_index')->nullable();
                $table->decimal('final_rotation', 10, 3)->nullable();
                $table->foreignId('winner_user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->boolean('winner_was_viewing')->default(false);
                $table->foreignId('winner_phrase_id')
                    ->nullable()
                    ->constrained('community_roulette_phrases')
                    ->nullOnDelete();
                $table->string('winner_phrase_text', 500)->nullable();
                $table->string('failure_reason', 500)->nullable();
                $table->timestamp('completed_at')->nullable();
                $table->timestamp('closed_at')->nullable();
                $table->timestamps();

                $table->index(['event_id', 'status']);
            });
        }

        if (! Schema::hasTable('community_roulette_previous_events')) {
            Schema::create('community_roulette_previous_events', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('room_id')
                    ->constrained('community_roulette_rooms')
                    ->cascadeOnDelete();
                $table->foreignId('event_id')
                    ->nullable()
                    ->constrained('events')
                    ->nullOnDelete();
                $table->unsignedTinyInteger('position');
                $table->string('event_name_snapshot');
                $table->dateTime('event_date_snapshot');
                $table->timestamps();

                $table->unique(['room_id', 'position']);
                $table->unique(['room_id', 'event_id']);
            });
        }

        if (! Schema::hasTable('community_roulette_slot_type_rules')) {
            Schema::create('community_roulette_slot_type_rules', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('room_id')
                    ->constrained('community_roulette_rooms')
                    ->cascadeOnDelete();
                $table->foreignId('slot_type_id')
                    ->nullable()
                    ->constrained('slot_types')
                    ->nullOnDelete();
                $table->string('slot_type_name_snapshot');
                $table->boolean('is_responsibility')->default(false);
                $table->boolean('is_hq')->default(false);
                $table->string('source', 16)->default('auto');
                $table->timestamps();

                $table->unique(['room_id', 'slot_type_id']);
            });
        }

        if (! Schema::hasTable('community_roulette_candidates')) {
            Schema::create('community_roulette_candidates', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('room_id')
                    ->constrained('community_roulette_rooms')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->string('nick_snapshot');
                $table->string('status_snapshot', 40)->nullable();
                $table->date('member_at_snapshot')->nullable();
                $table->char('current_slot_key', 26)->nullable();
                $table->string('current_slot_name')->nullable();
                $table->foreignId('current_slot_type_id')
                    ->nullable()
                    ->constrained('slot_types')
                    ->nullOnDelete();
                $table->unsignedTinyInteger('base_tickets')->default(4);
                $table->unsignedTinyInteger('tickets')->default(0);
                $table->unsignedTinyInteger('previous_responsibility_count')->default(0);
                $table->string('excluded_reason', 255)->nullable();
                $table->json('details')->nullable();
                $table->boolean('is_winner')->default(false);
                $table->timestamps();

                $table->unique(['room_id', 'user_id']);
                $table->index(['room_id', 'tickets']);
            });
        }

        if (! Schema::hasTable('community_roulette_viewers')) {
            Schema::create('community_roulette_viewers', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('room_id')
                    ->constrained('community_roulette_rooms')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->timestamp('last_seen_at')->index();
                $table->timestamps();

                $table->unique(['room_id', 'user_id']);
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('community_roulette_viewers');
        Schema::dropIfExists('community_roulette_candidates');
        Schema::dropIfExists('community_roulette_slot_type_rules');
        Schema::dropIfExists('community_roulette_previous_events');
        Schema::dropIfExists('community_roulette_rooms');
        Schema::dropIfExists('community_roulette_phrases');
    }
};
