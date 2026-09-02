<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_calendar_reservations', function (Blueprint $table): void {
            $table->id();
            $table->date('reserved_date')->unique();
            $table->foreignId('user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('reserved_for_nick');
            $table->string('comment', 120);
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->foreignId('updated_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();

            $table->index(['reserved_date', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_calendar_reservations');
    }
};
