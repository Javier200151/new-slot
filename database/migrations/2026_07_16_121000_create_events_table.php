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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('operation_id')
                ->constrained('operations')
                ->cascadeOnDelete();
            $table->dateTime('date');
            $table->unsignedInteger('duration')->nullable();
            $table->json('orbat')->nullable();
            $table->foreignId('event_status_id')
                ->constrained('event_status')
                ->restrictOnDelete();
            $table->string('ocap_url')->nullable();
            $table->timestamps();
            $table->softDeletes();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
