<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('infrastructure_settings')) {
            return;
        }

        if (! Schema::hasColumn('infrastructure_settings', 'tsviewer_server_id')) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->unsignedBigInteger('tsviewer_server_id')->nullable()->after('ts3_enabled');
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('infrastructure_settings') && Schema::hasColumn('infrastructure_settings', 'tsviewer_server_id')) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->dropColumn('tsviewer_server_id');
            });
        }
    }
};
