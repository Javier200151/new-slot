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
        Schema::create('enemy_faction_operation', function (Blueprint $table) {
            $table->foreignId('operation_id')
                ->constrained('operations')
                ->cascadeOnDelete();

            $table->foreignId('faction_id')
                ->constrained('factions')
                ->cascadeOnDelete();

            $table->primary(['operation_id', 'faction_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('enemy_faction_operation');
    }
};
