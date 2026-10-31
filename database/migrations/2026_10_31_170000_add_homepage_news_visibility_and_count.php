<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table): void {
            $table->unsignedTinyInteger('news_home_limit')->default(6);
            $table->json('news_visible_status_ids')->nullable();
        });

        $ids = DB::table('status')
            ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA'])
            ->pluck('id')->map(fn ($id): int => (int) $id)->all();

        DB::table('homepage_settings')->whereNull('news_visible_status_ids')
            ->update(['news_visible_status_ids' => json_encode($ids)]);
    }

    public function down(): void
    {
        Schema::table('homepage_settings', function (Blueprint $table): void {
            $table->dropColumn(['news_home_limit', 'news_visible_status_ids']);
        });
    }
};
