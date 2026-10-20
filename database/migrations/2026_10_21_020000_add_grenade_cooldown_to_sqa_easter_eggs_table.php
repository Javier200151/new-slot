<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sqa_easter_eggs', function (Blueprint $table): void {
            $table->unsignedInteger('cooldown_seconds')->default(120);
        });
    }

    public function down(): void
    {
        Schema::table('sqa_easter_eggs', function (Blueprint $table): void {
            $table->dropColumn('cooldown_seconds');
        });
    }
};
