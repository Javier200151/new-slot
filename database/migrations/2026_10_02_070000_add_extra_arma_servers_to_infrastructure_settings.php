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

        if (! Schema::hasColumn('infrastructure_settings', 'extra_arma_servers')) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->json('extra_arma_servers')->nullable();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('infrastructure_settings') && Schema::hasColumn('infrastructure_settings', 'extra_arma_servers')) {
            Schema::table('infrastructure_settings', function (Blueprint $table): void {
                $table->dropColumn('extra_arma_servers');
            });
        }
    }
};
