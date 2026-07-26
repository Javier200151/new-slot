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
        Schema::create('addon_presets', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->text('description')->nullable();
            $table->timestamps();
        });

        Schema::create('addon_preset_addon', function (Blueprint $table) {
            $table->foreignId('addon_preset_id')
                ->constrained('addon_presets')
                ->cascadeOnDelete();

            $table->foreignId('addon_id')
                ->constrained('addons')
                ->cascadeOnDelete();

            $table->primary(['addon_preset_id', 'addon_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('addon_preset_addon');
        Schema::dropIfExists('addon_presets');
    }
};
