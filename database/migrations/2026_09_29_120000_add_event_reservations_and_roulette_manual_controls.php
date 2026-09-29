<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('events', 'reservations_enabled')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->boolean('reservations_enabled')
                    ->default(false)
                    ->after('multiclans');
            });
        }

        if (! Schema::hasTable('event_reservations')) {
            Schema::create('event_reservations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('event_id')
                    ->constrained('events')
                    ->cascadeOnDelete();
                $table->foreignId('user_id')
                    ->constrained('users')
                    ->cascadeOnDelete();
                $table->foreignId('created_by')
                    ->nullable()
                    ->constrained('users')
                    ->nullOnDelete();
                $table->timestamps();

                $table->unique(['event_id', 'user_id']);
                $table->index(['event_id', 'created_at']);
            });
        }

        if (! Schema::hasColumn('community_roulette_candidates', 'manual_ticket_adjustment')) {
            Schema::table('community_roulette_candidates', function (Blueprint $table): void {
                $table->smallInteger('manual_ticket_adjustment')
                    ->default(0)
                    ->after('tickets');
            });
        }

        if (! Schema::hasColumn('community_roulette_candidates', 'is_exceptional')) {
            Schema::table('community_roulette_candidates', function (Blueprint $table): void {
                $table->boolean('is_exceptional')
                    ->default(false)
                    ->after('manual_ticket_adjustment');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('community_roulette_candidates', 'is_exceptional')) {
            Schema::table('community_roulette_candidates', function (Blueprint $table): void {
                $table->dropColumn('is_exceptional');
            });
        }

        if (Schema::hasColumn('community_roulette_candidates', 'manual_ticket_adjustment')) {
            Schema::table('community_roulette_candidates', function (Blueprint $table): void {
                $table->dropColumn('manual_ticket_adjustment');
            });
        }

        Schema::dropIfExists('event_reservations');

        if (Schema::hasColumn('events', 'reservations_enabled')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('reservations_enabled');
            });
        }
    }
};
