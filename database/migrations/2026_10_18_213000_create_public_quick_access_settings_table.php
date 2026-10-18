<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (Schema::hasTable('public_quick_access_settings')) return;

        Schema::create('public_quick_access_settings', function (Blueprint $table): void {
            $table->id();
            $table->json('items');
            $table->string('position', 8)->default('right');
            $table->boolean('is_enabled')->default(true);
            $table->timestamps();
        });

        DB::table('public_quick_access_settings')->insert([
            'items' => json_encode(\App\Support\PublicQuickAccessNavigation::defaultItems(), JSON_UNESCAPED_UNICODE),
            'position' => 'right',
            'is_enabled' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('public_quick_access_settings');
    }
};
