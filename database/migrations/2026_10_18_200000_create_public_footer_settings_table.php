<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('public_footer_settings')) {
            return;
        }

        Schema::create('public_footer_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('items');
            $table->timestamps();
        });

        DB::table('public_footer_settings')->insert([
            'items' => json_encode(\App\Support\PublicFooterNavigation::defaultItems(), JSON_UNESCAPED_UNICODE),
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_footer_settings');
    }
};
