<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('community_polls', function (Blueprint $table): void {
            $table->id();
            $table->string('title', 180);
            $table->text('description')->nullable();
            $table->boolean('is_published')->default(false)->index();
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->timestamps();
            $table->softDeletes();
        });

        Schema::create('community_poll_options', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_poll_id')
                ->constrained('community_polls')
                ->cascadeOnDelete();
            $table->string('label', 180);
            $table->unsignedInteger('sort_order')->default(10);
            $table->timestamps();
        });

        Schema::create('community_poll_votes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_poll_id')
                ->constrained('community_polls')
                ->cascadeOnDelete();
            $table->foreignId('community_poll_option_id')
                ->constrained('community_poll_options')
                ->cascadeOnDelete();
            $table->foreignId('user_id')
                ->constrained('users')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['community_poll_id', 'user_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_poll_votes');
        Schema::dropIfExists('community_poll_options');
        Schema::dropIfExists('community_polls');
    }
};
