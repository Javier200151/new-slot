<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('streamers', function (Blueprint $table) {
            $table->renameColumn(
                'twich_channel',
                'twitch_channel'
            );

            $table
                ->string('twitch_user_id')
                ->nullable()
                ->after('twitch_channel')
                ->index();

            $table
                ->string('youtube_channel_id')
                ->nullable()
                ->after('youtube_channel')
                ->index();

            $table
                ->string('website_url')
                ->nullable()
                ->after('other_channel');
        });

        Schema::table('streams', function (Blueprint $table) {
            $table
                ->string('platform', 20)
                ->nullable()
                ->after('streamer_id');

            $table
                ->boolean('enabled')
                ->default(false)
                ->after('stream_url');

            $table
                ->string('title')
                ->nullable()
                ->after('enabled');

            $table
                ->timestamp('started_at')
                ->nullable()
                ->after('title');

            $table
                ->timestamp('ended_at')
                ->nullable()
                ->after('started_at');
        });
    }

    public function down(): void
    {
        Schema::table('streams', function (Blueprint $table) {
            $table->dropColumn([
                'platform',
                'enabled',
                'title',
                'started_at',
                'ended_at',
            ]);
        });

        Schema::table('streamers', function (Blueprint $table) {
            $table->dropColumn([
                'twitch_user_id',
                'youtube_channel_id',
                'website_url',
            ]);

            $table->renameColumn(
                'twitch_channel',
                'twich_channel'
            );
        });
    }
};