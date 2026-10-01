<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('activities', 'addon_package_url')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->string('addon_package_url', 1000)->nullable()->after('addons');
            });
        }

        if (! Schema::hasColumn('events', 'briefing_extra')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->text('briefing_extra')->nullable()->after('reservations_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasColumn('activities', 'addon_package_url')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->dropColumn('addon_package_url');
            });
        }

        if (Schema::hasColumn('events', 'briefing_extra')) {
            Schema::table('events', function (Blueprint $table): void {
                $table->dropColumn('briefing_extra');
            });
        }
    }
};
