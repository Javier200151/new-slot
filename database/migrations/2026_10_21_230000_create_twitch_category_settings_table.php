<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('twitch_category_settings')) {
            return;
        }

        Schema::create('twitch_category_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('allowed_categories');
            $table->timestamps();
        });

        DB::table('twitch_category_settings')->insert([
            'id' => 1,
            'allowed_categories' => json_encode(['Arma 3', 'Arma Reforger'], JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('twitch_category_settings');
    }
};
