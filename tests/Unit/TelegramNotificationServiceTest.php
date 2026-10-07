<?php

namespace Tests\Unit;

use App\Models\CommunityPost;
use App\Models\ForumCategory;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\MemberProcedures\TelegramNotificationService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class TelegramNotificationServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('newslot.procedures.telegram.enabled', true);
        config()->set('newslot.procedures.telegram.bot_token', '123:test-token');
        config()->set('newslot.procedures.telegram.base_url', 'https://telegram.test');
        config()->set('app.url', 'https://www.squadalpha.es');

        Http::fake([
            'https://telegram.test/bot123:test-token/sendMessage' => Http::response([
                'ok' => true,
                'result' => [
                    'message_id' => 90,
                    'chat' => ['id' => -1001234567890, 'title' => 'ALPHA Network', 'type' => 'supergroup'],
                ],
            ], 200),
        ]);
    }

    public function test_recruit_entry_uses_configurable_template_and_single_random_closing(): void
    {
        $setting = new MemberProcedureSetting([
            'telegram_network_chat_id' => '-1001234567890',
            'telegram_recruit_update_template' => "Actualización de reclutas:\n\n{{cambio}}\n\n{{cierre}}",
            'telegram_recruit_entry_endings' => [
                ['text' => 'Como a los demás, empezaréis a ver a {{nick}} pronto en los operativos\\!'],
            ],
        ]);

        $user = new User(['nick' => 'Moon']);
        app(TelegramNotificationService::class)->sendRecruitUpdate($user, $setting, 'entry');

        Http::assertSent(function (Request $request): bool {
            $text = (string) $request['text'];

            return $request['parse_mode'] === 'MarkdownV2'
                && str_contains($text, 'Nuevo recluta:')
                && str_contains($text, '\\- Moon')
                && str_contains($text, 'ver a Moon pronto en los operativos\\!');
        });
    }

    public function test_veterancies_are_grouped_gold_silver_bronze_in_one_message(): void
    {
        $setting = new MemberProcedureSetting([
            'telegram_network_chat_id' => '-1001234567890',
            'telegram_veterancy_template' => "{{foro_url}}\n\nLa administración tiene el orgullo de galardonar:\n\n{{veteranias}}",
        ]);

        $post = new CommunityPost(['channel' => 'personal', 'title' => 'Veteranias']);
        $post->id = 5;
        $post->setRelation('forumCategory', new ForumCategory(['slug' => 'personal']));

        app(TelegramNotificationService::class)->sendVeterancyUpdate($post, [
            ['nick' => 'Cetme', 'level' => 'bronze'],
            ['nick' => 'Dragut', 'level' => 'gold'],
            ['nick' => '7orres', 'level' => 'silver'],
        ], $setting);

        Http::assertSent(function (Request $request): bool {
            $text = (string) $request['text'];
            $gold = strpos($text, '🥇 a SQA Dragut');
            $silver = strpos($text, '🥈 a SQA 7orres');
            $bronze = strpos($text, '🥉 a SQA Cetme');

            return $request['parse_mode'] === 'MarkdownV2'
                && $gold !== false
                && $silver !== false
                && $bronze !== false
                && $gold < $silver
                && $silver < $bronze
                && str_contains($text, '/area/foro/personal/5');
        });
    }
}
