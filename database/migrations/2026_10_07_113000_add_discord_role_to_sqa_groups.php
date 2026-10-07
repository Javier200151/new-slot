<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('sqa_groups') && ! Schema::hasColumn('sqa_groups', 'discord_role_id')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->string('discord_role_id', 32)->nullable()->after('has_coordinator_role');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('sqa_groups') && Schema::hasColumn('sqa_groups', 'discord_role_id')) {
            Schema::table('sqa_groups', function (Blueprint $table): void {
                $table->dropColumn('discord_role_id');
            });
        }
    }
};
