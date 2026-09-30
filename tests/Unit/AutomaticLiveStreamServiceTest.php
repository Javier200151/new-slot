<?php

namespace Tests\Unit;

use App\Models\Streamer;
use App\Models\User;
use App\Services\AutomaticLiveStreamService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class AutomaticLiveStreamServiceTest extends TestCase
{
    public function test_it_detects_twitch_and_youtube_live_streams_automatically(): void
    {
        config()->set('newslot.streaming.automatic_live', true);
        config()->set('newslot.streaming.live_cache_seconds', 60);
        config()->set('services.twitch.client_id', 'client-id');
        config()->set('services.twitch.client_secret', 'client-secret');

        Cache::flush();

        Http::fake([
            'https://id.twitch.tv/oauth2/token' => Http::response([
                'access_token' => 'token-123',
                'expires_in' => 3600,
            ]),
            'https://api.twitch.tv/helix/streams*' => Http::response([
                'data' => [[
                    'id' => 'stream-99',
                    'user_id' => 'twitch-user-1',
                    'user_login' => 'alphalive',
                    'title' => 'Operativo en directo',
                    'started_at' => '2026-09-30T19:30:00Z',
                ]],
            ]),
            'https://www.youtube.com/channel/UC12345678901234567890/live' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <link rel="canonical" href="https://www.youtube.com/watch?v=AbCdEf12345">
                        <meta name="title" content="Directo YouTube SQA">
                    </head>
                    <body>
                        <script>{"videoId":"AbCdEf12345","isLiveNow":true,"startTimestamp":"2026-09-30T19:35:00Z"}</script>
                    </body>
                </html>
                HTML),
        ]);

        $streamer = new Streamer();
        $streamer->forceFill([
            'id' => 5,
            'enable' => true,
            'twitch_channel' => 'https://www.twitch.tv/alphalive',
            'twitch_user_id' => 'twitch-user-1',
            'youtube_channel' => 'https://www.youtube.com/@alphalive',
            'youtube_channel_id' => 'UC12345678901234567890',
        ]);
        $streamer->setRelation('user', new User(['nick' => 'AlphaLive']));

        $streams = app(AutomaticLiveStreamService::class)
            ->discover(collect([$streamer]), force: true);

        $this->assertCount(2, $streams);
        $this->assertSame(['twitch', 'youtube'], $streams->pluck('platform')->sort()->values()->all());
        $this->assertSame(
            'https://www.twitch.tv/alphalive',
            $streams->firstWhere('platform', 'twitch')['url'],
        );
        $this->assertSame(
            'https://www.youtube.com/watch?v=AbCdEf12345',
            $streams->firstWhere('platform', 'youtube')['url'],
        );
    }

    public function test_youtube_channel_is_not_considered_live_without_is_live_now(): void
    {
        config()->set('newslot.streaming.automatic_live', true);
        Cache::flush();

        Http::fake([
            'https://www.youtube.com/channel/UC12345678901234567890/live' => Http::response(<<<'HTML'
                <html>
                    <head>
                        <link rel="canonical" href="https://www.youtube.com/watch?v=AbCdEf12345">
                        <meta name="title" content="Vídeo archivado">
                    </head>
                    <body>{"videoId":"AbCdEf12345","isLiveNow":false}</body>
                </html>
                HTML),
        ]);

        $streamer = new Streamer();
        $streamer->forceFill([
            'id' => 6,
            'enable' => true,
            'youtube_channel' => 'https://www.youtube.com/@offline',
            'youtube_channel_id' => 'UC12345678901234567890',
        ]);

        $streams = app(AutomaticLiveStreamService::class)
            ->discover(collect([$streamer]), force: true);

        $this->assertTrue($streams->isEmpty());
    }
}
