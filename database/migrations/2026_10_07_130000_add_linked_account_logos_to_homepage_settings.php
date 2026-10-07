<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            return;
        }

        $missingDiscordLogo = ! Schema::hasColumn('homepage_settings', 'discord_account_logo');
        $missingSteamLogo = ! Schema::hasColumn('homepage_settings', 'steam_account_logo');

        if (! $missingDiscordLogo && ! $missingSteamLogo) {
            return;
        }

        Schema::table('homepage_settings', function (Blueprint $table) use ($missingDiscordLogo, $missingSteamLogo): void {
            if ($missingDiscordLogo) {
                $table->string('discord_account_logo')->nullable();
            }

            if ($missingSteamLogo) {
                $table->string('steam_account_logo')->nullable();
            }
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('homepage_settings')) {
            return;
        }

        $columns = array_values(array_filter([
            Schema::hasColumn('homepage_settings', 'discord_account_logo') ? 'discord_account_logo' : null,
            Schema::hasColumn('homepage_settings', 'steam_account_logo') ? 'steam_account_logo' : null,
        ]));

        if ($columns === []) {
            return;
        }

        Schema::table('homepage_settings', function (Blueprint $table) use ($columns): void {
            $table->dropColumn($columns);
        });
    }
};
