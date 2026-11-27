<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_orbat_group_settings', function (Blueprint $table): void {
            $table->json('color_team_titles')->nullable();
        });

        Schema::table('event_orbat_group_command_history', function (Blueprint $table): void {
            $table->json('previous_color_team_titles')->nullable();
            $table->json('new_color_team_titles')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_orbat_group_command_history', function (Blueprint $table): void {
            $table->dropColumn(['previous_color_team_titles', 'new_color_team_titles']);
        });
        Schema::table('event_orbat_group_settings', function (Blueprint $table): void {
            $table->dropColumn('color_team_titles');
        });
    }
};
