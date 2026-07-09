<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('factions', function (Blueprint $table) {
            $table->foreignId('side_id')
                ->nullable()
                ->after('id')
                ->constrained('sides')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('factions', function (Blueprint $table) {
            $table->dropConstrainedForeignId('side_id');
        });
    }
};
