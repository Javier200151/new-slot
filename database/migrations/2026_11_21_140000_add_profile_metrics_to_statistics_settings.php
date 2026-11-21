<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('statistics_settings', function (Blueprint $table): void {
            $table->json('profile_metrics')->nullable();
        });

        // Se mantiene únicamente la tarjeta Rol más jugado por defecto.
        DB::table('statistics_settings')->update([
            'profile_metrics' => json_encode(['favorite_role'], JSON_THROW_ON_ERROR),
        ]);
    }

    public function down(): void
    {
        Schema::table('statistics_settings', function (Blueprint $table): void {
            $table->dropColumn('profile_metrics');
        });
    }
};
