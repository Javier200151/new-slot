<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'discord_username')) {
                $table->string('discord_username', 64)->nullable();
            }

            if (! Schema::hasColumn('users', 'discord_linked_at')) {
                $table->timestamp('discord_linked_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'steam_linked_at')) {
                $table->timestamp('steam_linked_at')->nullable();
            }

            if (! Schema::hasColumn('users', 'steam_profile_url')) {
                $table->string('steam_profile_url', 255)->nullable();
            }
        });

        DB::table('users')
            ->whereNotNull('discord_id')
            ->whereRaw("TRIM(discord_id) = ''")
            ->update(['discord_id' => null]);

        $duplicates = DB::table('users')
            ->select('discord_id')
            ->whereNotNull('discord_id')
            ->groupBy('discord_id')
            ->havingRaw('COUNT(*) > 1')
            ->orderBy('discord_id')
            ->limit(10)
            ->pluck('discord_id');

        if ($duplicates->isNotEmpty()) {
            throw new \RuntimeException(
                'No se puede crear el índice UNIQUE de users.discord_id porque existen '
                . 'Discord ID duplicados: '
                . $duplicates->implode(', ')
                . '. Corrige los usuarios afectados y vuelve a ejecutar la migración.'
            );
        }

        if (! Schema::hasIndex('users', 'users_discord_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->unique('discord_id', 'users_discord_id_unique');
            });
        }

        if (Schema::hasIndex('users', 'users_discord_id_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropIndex('users_discord_id_index');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasIndex('users', 'users_discord_id_index')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->index('discord_id', 'users_discord_id_index');
            });
        }

        if (Schema::hasIndex('users', 'users_discord_id_unique')) {
            Schema::table('users', function (Blueprint $table): void {
                $table->dropUnique('users_discord_id_unique');
            });
        }

        Schema::table('users', function (Blueprint $table): void {
            foreach ([
                'discord_username',
                'discord_linked_at',
                'steam_linked_at',
                'steam_profile_url',
            ] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
