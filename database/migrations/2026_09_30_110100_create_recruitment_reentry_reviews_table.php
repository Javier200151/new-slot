<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('recruitment_reentry_reviews', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('user_id')
                ->constrained('users')
                ->restrictOnDelete();

            /* Misma técnica que en recruitment_periods: una sola revisión pendiente. */
            $table->foreignId('pending_user_id')
                ->nullable()
                ->unique()
                ->constrained('users')
                ->restrictOnDelete();

            $table->foreignId('previous_period_id')
                ->constrained('recruitment_periods')
                ->restrictOnDelete();

            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->string('resolution', 40)->nullable();

            $table->foreignId('resolved_by_user_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();

            $table->text('resolution_note')->nullable();
            $table->timestamps();

            $table->index(['user_id', 'resolved_at'], 'recruitment_reentry_user_resolved_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_reentry_reviews');
    }
};
