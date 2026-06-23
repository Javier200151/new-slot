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
        Schema::create('operation_slot_group', function (Blueprint $table) {
            $table->id();

            $table->foreignId('operation_id')->constrained('operations');

            $table->string('name');

            $table->integer('display_order')->default(0);

            $table->boolean('enable')->default(true);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('operation_slot_group');
    }
};
