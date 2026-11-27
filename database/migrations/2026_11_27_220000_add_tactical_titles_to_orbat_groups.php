<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_orbat_group_settings', function (Blueprint $table): void {
            $table->string('custom_title', 80)->nullable()->after('group_name');
        });

        Schema::table('event_orbat_group_command_history', function (Blueprint $table): void {
            $table->string('previous_title', 80)->nullable();
            $table->string('new_title', 80)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('event_orbat_group_command_history', function (Blueprint $table): void {
            $table->dropColumn(['previous_title', 'new_title']);
        });

        Schema::table('event_orbat_group_settings', function (Blueprint $table): void {
            $table->dropColumn('custom_title');
        });
    }
};
