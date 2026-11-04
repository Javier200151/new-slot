<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_news_batches', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('event_id')->constrained('events')->cascadeOnDelete();
            $table->json('content');
            $table->timestamp('first_occurred_at');
            $table->timestamp('last_occurred_at');
            $table->timestamp('viewed_at')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'last_occurred_at']);
            $table->index(['user_id', 'event_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_news_batches');
    }
};
