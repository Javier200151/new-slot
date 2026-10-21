<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('twitch_category_settings') && ! Schema::hasColumn('twitch_category_settings', 'title_keywords')) {
            Schema::table('twitch_category_settings', function (Blueprint $table): void {
                $table->json('title_keywords')->nullable();
                $table->json('excluded_vod_ids')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('twitch_category_settings') && Schema::hasColumn('twitch_category_settings', 'title_keywords')) {
            Schema::table('twitch_category_settings', fn (Blueprint $table) => $table->dropColumn(['title_keywords', 'excluded_vod_ids']));
        }
    }
};
