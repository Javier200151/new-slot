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
        Schema::create('event_slot_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('event_slot_id')
                ->constrained('event_slots')
                ->cascadeOnDelete();
            $table->foreignId('ally_id')
                ->nullable()
                ->constrained('allies')
                ->nullOnDelete();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('action');
            $table->char('from_slot_key', 26)->nullable();
            $table->string('from_slot_name')->nullable();
            $table->foreignId('from_slot_type_id')
                ->nullable()
                ->constrained('slot_types')
                ->nullOnDelete();
            $table->string('from_slot_group')->nullable();
            $table->foreignId('from_army_id')
                ->nullable()
                ->constrained('armies')
                ->nullOnDelete();
            $table->char('to_slot_key', 26)->nullable();
            $table->string('to_slot_name')->nullable();
            $table->foreignId('to_slot_type_id')
                ->nullable()
                ->constrained('slot_types')
                ->nullOnDelete();
            $table->string('to_slot_group')->nullable();
            $table->foreignId('to_army_id')
                ->nullable()
                ->constrained('armies')
                ->nullOnDelete();
            $table->foreignId('changed_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['event_slot_id', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('event_slot_history');
    }
};
