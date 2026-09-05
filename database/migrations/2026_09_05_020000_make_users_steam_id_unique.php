<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Los valores vacíos no identifican a ningún usuario y una restricción
        // UNIQUE solo puede permitirlos repetidos si se almacenan como NULL.
        DB::table('users')
            ->whereNotNull('steam_id')
            ->whereRaw("TRIM(steam_id) = ''")
            ->update(['steam_id' => null]);

        $duplicates = DB::table('users')
            ->select('steam_id')
            ->whereNotNull('steam_id')
            ->groupBy('steam_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('steam_id')
            ->limit(10)
            ->pluck('steam_id');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'No se puede crear el índice UNIQUE de users.steam_id porque existen '
                . 'Steam ID duplicados: '
                . $duplicates->implode(', ')
                . '. Corrige los usuarios afectados y vuelve a ejecutar la migración.'
            );
        }

        if (! Schema::hasIndex('users', 'users_steam_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->unique('steam_id', 'users_steam_id_unique');
            });
        }

        if (Schema::hasIndex('users', 'users_steam_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropIndex('users_steam_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('users', 'users_steam_id_index')) {
            Schema::table('users', function (Blueprint $table) {
                $table->index('steam_id', 'users_steam_id_index');
            });
        }

        if (Schema::hasIndex('users', 'users_steam_id_unique')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropUnique('users_steam_id_unique');
            });
        }
    }
};
