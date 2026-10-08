<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('homepage_settings') || Schema::hasColumn('homepage_settings', 'telegram_account_logo')) {
            return;
        }

        Schema::table('homepage_settings', function (Blueprint $table): void {
            $table->string('telegram_account_logo')->nullable()->after('steam_account_logo');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('homepage_settings') || ! Schema::hasColumn('homepage_settings', 'telegram_account_logo')) {
            return;
        }

        Schema::table('homepage_settings', function (Blueprint $table): void {
            $table->dropColumn('telegram_account_logo');
        });
    }
};
