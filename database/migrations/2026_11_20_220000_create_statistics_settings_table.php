<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('statistics_settings', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_enabled')->default(true);
            $table->json('allowed_statuses')->nullable();
            $table->boolean('allow_admins')->default(true);
            $table->boolean('show_user_profiles')->default(true);
            $table->timestamps();
        });

        DB::table('statistics_settings')->insert([
            'is_enabled' => true,
            'allowed_statuses' => json_encode(['ACTIVO', 'RESERVA'], JSON_THROW_ON_ERROR),
            'allow_admins' => true,
            'show_user_profiles' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('statistics_settings');
    }
};
