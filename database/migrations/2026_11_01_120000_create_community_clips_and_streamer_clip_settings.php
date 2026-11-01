<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table): void {
            $table->boolean('auto_import_clips')->default(false);
            $table->string('clip_import_mode', 12)->default('all');
        });

        Schema::create('community_clips', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('streamer_id')->nullable()->constrained('streamers')->nullOnDelete();
            $table->string('platform', 16);
            $table->string('external_id', 150);
            $table->string('source', 16)->default('manual');
            $table->string('title')->nullable();
            $table->string('url', 600);
            $table->string('thumbnail_url', 800)->nullable();
            $table->string('broadcaster_name', 120)->nullable();
            $table->string('game_id', 40)->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->boolean('is_visible')->default(true);
            $table->timestamp('clip_created_at')->nullable();
            $table->softDeletes();
            $table->timestamps();
            $table->unique(['platform', 'external_id']);
            $table->index(['is_visible', 'clip_created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('community_clips');
        Schema::table('streamers', function (Blueprint $table): void {
            $table->dropColumn(['auto_import_clips', 'clip_import_mode']);
        });
    }
};
