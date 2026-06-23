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
        Schema::create('slot_types_status', function (Blueprint $table) {
            $table->id();

            $table->foreignId('slot_type_id')->primary()->constrained('slot_types');

            $table->foreignId('status_id')->primary()->constrained('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('slot_types_status');
    }
};
