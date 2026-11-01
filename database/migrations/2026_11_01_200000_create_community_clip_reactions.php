<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('community_clip_likes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_clip_id')->constrained('community_clips')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
            $table->unique(['community_clip_id', 'user_id']);
        });
        Schema::create('community_clip_comments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('community_clip_id')->constrained('community_clips')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            $table->timestamps();
            $table->index(['community_clip_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_clip_comments');
        Schema::dropIfExists('community_clip_likes');
    }
};
