<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const TABLES = [
        'public_navigation_settings',
        'public_footer_settings',
        'public_quick_access_settings',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'show_all_to_admins')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->boolean('show_all_to_admins')->default(false);
                });
            }
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'show_all_to_admins')) {
                Schema::table($table, function (Blueprint $blueprint): void {
                    $blueprint->dropColumn('show_all_to_admins');
                });
            }
        }
    }
};
