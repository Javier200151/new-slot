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
        Schema::create('event_slots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_id')
                ->constrained('events')
                ->cascadeOnDelete();
            $table->char('slot_key', 26);
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('ally_id')
                ->nullable()
                ->constrained('allies')
                ->nullOnDelete();
            $table->string('name');
            $table->foreignId('slot_type_id')
                ->constrained('slot_types')
                ->restrictOnDelete();
            $table->string('slot_group');
            $table->foreignId('army_id')
                ->constrained('armies')
                ->restrictOnDelete();
            $table->timestamps();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->unique(['event_id', 'slot_key']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_slots');
    }
};
