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
            $table->dropConstrainedForeignId('platform_id');
            $table->dropConstrainedForeignId('faction_id');

            $table->dropColumn([
                'ocap_url',
                'duration_minutes',
                'side',
            ]);

            $table->longText('orbat')
                ->nullable()
                ->after('radio');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table) {
            $table->dropColumn('orbat');

            $table->foreignId('platform_id')
                ->nullable()
                ->after('campaign_id')
                ->constrained('platforms')
                ->nullOnDelete();

            $table->foreignId('faction_id')
                ->nullable()
                ->after('period_id')
                ->constrained('factions')
                ->nullOnDelete();

            $table->string('ocap_url')
                ->nullable()
                ->after('ocap');

            $table->unsignedSmallInteger('duration_minutes')
                ->nullable()
                ->after('editor_id');

            $table->string('side')
                ->nullable()
                ->after('day_or_night');
        });
    }
};
