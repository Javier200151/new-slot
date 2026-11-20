<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->string('download_url', 2048)->nullable()->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('addons', function (Blueprint $table): void {
            $table->dropColumn('download_url');
        });
    }
};
