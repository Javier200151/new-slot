<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streams', function (Blueprint $table): void {
            // Un directo automático puede no estar asociado a un evento.
            $table->foreignId('event_id')->nullable()->change();
            $table->boolean('is_vod')->default(false);
            $table->boolean('visible_on_home')->default(true);
            $table->boolean('auto_detected')->default(false);
            $table->string('twitch_stream_id')->nullable()->unique();
            $table->string('twitch_video_id')->nullable()->unique();
            $table->text('thumbnail_url')->nullable();
            $table->timestamp('published_at')->nullable();
            $table->timestamp('twitch_last_seen_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table): void {
            $table->dropUnique(['twitch_stream_id']);
            $table->dropUnique(['twitch_video_id']);
            $table->dropColumn([
                'is_vod', 'visible_on_home', 'auto_detected',
                'twitch_stream_id', 'twitch_video_id',
                'thumbnail_url', 'published_at', 'twitch_last_seen_at',
            ]);
        });
        // event_id se mantiene nullable para no perder VODs vinculados.
    }
};
