<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('homepage_news', function (Blueprint $table): void {
            $table->string('category', 40)->default('comunicado')->index();
            $table->string('source_type', 20)->default('manual');
            $table->string('source_key', 191)->nullable()->unique();
        });
    }

    public function down(): void
    {
        Schema::table('homepage_news', function (Blueprint $table): void {
            $table->dropUnique(['source_key']);
            $table->dropIndex(['category']);
            $table->dropColumn(['category', 'source_type', 'source_key']);
        });
    }
};
