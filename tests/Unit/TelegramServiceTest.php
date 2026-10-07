<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\TelegramService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramServiceTest extends TestCase
{
    private function configure(): MemberProcedureSetting
    {
        config()->set('newslot.procedures.telegram.enabled', true);
        config()->set('newslot.procedures.telegram.bot_token', '123:test-token');
        config()->set('newslot.procedures.telegram.base_url', 'https://telegram.test');

        return new MemberProcedureSetting([
            'telegram_network_chat_id' => '-1001234567890',
        ]);
    }

    public function test_message_can_be_sent_to_network_chat(): void
    {
        $this->configure();

        Http::fake([
            'https://telegram.test/bot123:test-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 77,
                    'chat' => ['id' => -1001234567890, 'title' => 'ALPHA Network', 'type' => 'supergroup'],
                ],
            ], 200),
        ]);

        $result = app(TelegramService::class)->sendMessage('-1001234567890', 'Prueba NewSlot');

        $this->assertSame(77, $result['message_id']);
        $this->assertSame('-1001234567890', $result['chat_id']);
        $this->assertSame('ALPHA Network', $result['chat_title']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://telegram.test/bot123:test-token/sendMessage'
            && $request['chat_id'] === '-1001234567890'
            && $request['text'] === 'Prueba NewSlot');
    }

    public function test_connection_checks_bot_and_network_chat(): void
    {
        $setting = $this->configure();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if (str_ends_with($url, '/getMe')) {
                return Http::response(['ok' => true, 'result' => ['id' => 42, 'username' => 'sqa_bot']], 200);
            }

            if (str_ends_with($url, '/getChat')) {
                return Http::response([
                    'ok' => true,
                    'result' => [
                        'id' => -1001234567890,
                        'title' => 'ALPHA Network',
                        'type' => 'supergroup',
                    ],
                ], 200);
            }

            if (str_ends_with($url, '/getChatMember')) {
                return Http::response([
                    'ok' => true,
                    'result' => ['status' => 'administrator'],
                ], 200);
            }

            return Http::response(['ok' => false, 'description' => 'Unexpected request'], 500);
        });

        $result = app(TelegramService::class)->testConnection($setting);

        $this->assertSame('sqa_bot', $result['bot_name']);
        $this->assertSame('ALPHA Network', $result['chats']['network']['title']);
    }

    public function test_recent_updates_can_be_used_to_discover_chat_ids(): void
    {
        $this->configure();

        Http::fake([
            'https://telegram.test/bot123:test-token/getUpdates' => Http::response([
                'ok' => true,
                'result' => [
                    ['update_id' => 1, 'message' => ['chat' => ['id' => -1001234567890, 'title' => 'ALPHA Network', 'type' => 'supergroup']]],
                    ['update_id' => 2, 'my_chat_member' => ['chat' => ['id' => -1009876543210, 'title' => 'Otro chat', 'type' => 'supergroup']]],
                ],
            ], 200),
        ]);

        $options = app(TelegramService::class)->discoverChats();

        $this->assertSame('ALPHA Network · -1001234567890', $options['-1001234567890']);
        $this->assertSame('Otro chat · -1009876543210', $options['-1009876543210']);
    }
}
