<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_news', function (Blueprint $table): void {
            $table->json('veterancy_groups')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_news', function (Blueprint $table): void {
            $table->dropColumn('veterancy_groups');
        });
    }
};
