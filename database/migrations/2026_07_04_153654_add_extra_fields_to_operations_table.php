<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            //Nuevos campos en operations

            $table->foreignId('map_id')
                ->nullable()
                ->constrained('maps')
                ->nullOnDelete();
            $table->foreignId('period_id')
                ->nullable()
                ->constrained('periods')
                ->nullOnDelete();
            $table->foreignId('faction_id')
                ->nullable()
                ->constrained('factions')
                ->nullOnDelete();
            $table->foreignId('editor_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->unsignedSmallInteger('duration_minutes')
                ->nullable();
            $table->enum('day_or_night', ['day', 'night'])
                ->nullable();
            $table->string('side')
                ->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            //
            $table->dropConstrainedForeignId('map_id');
            $table->dropConstrainedForeignId('period_id');
            $table->dropConstrainedForeignId('faction_id');
            $table->dropConstrainedForeignId('editor_id');
            $table->dropColumn([
                'duration_minutes',
                'day_or_night',
                'side',
            ]);
        });
    }
};
