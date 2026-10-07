<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\MemberProcedures\DiscordService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordServiceTest extends TestCase
{
    private function configure(): MemberProcedureSetting
    {
        config()->set('newslot.procedures.discord.enabled', true);
        config()->set('newslot.procedures.discord.bot_token', 'test-token');
        config()->set('newslot.procedures.discord.base_url', 'https://discord.test/api/v10');

        return new MemberProcedureSetting([
            'discord_guild_id' => '123456789012345678',
            'discord_recruit_role_id' => '223456789012345678',
            'discord_alpha_role_id' => '323456789012345678',
            'discord_reserve_role_id' => '423456789012345678',
            'discord_invite_channel_id' => '823456789012345678',
            'discord_bot_nickname' => 'NewSlot SQA',
            'discord_alpha_nickname_prefix' => '[=ALPHA=] ',
        ]);
    }

    public function test_alpha_sync_removes_recruit_adds_alpha_and_updates_nickname(): void
    {
        $setting = $this->configure();
        $user = new User([
            'nick' => 'Speirs',
            'discord_id' => '523456789012345678',
        ]);
        $memberGets = 0;

        Http::fake(function (Request $request) use (&$memberGets) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/members/523456789012345678')) {
                $memberGets++;

                return $memberGets === 1
                    ? Http::response(['roles' => ['223456789012345678'], 'nick' => 'Speirs'], 200)
                    : Http::response(['roles' => ['323456789012345678'], 'nick' => 'Speirs'], 200);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, '/roles/223456789012345678')) {
                return Http::response([], 204);
            }

            if ($request->method() === 'PUT' && str_ends_with($url, '/roles/323456789012345678')) {
                return Http::response([], 204);
            }

            if ($request->method() === 'PATCH' && str_ends_with($url, '/members/523456789012345678')) {
                return Http::response(['roles' => ['323456789012345678'], 'nick' => '[=ALPHA=] Speirs'], 200);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $result = app(DiscordService::class)->setAlpha($user, $setting, 'Alta de calavera');

        $this->assertSame('roles_updated', $result['action']);
        $this->assertSame('323456789012345678', $result['added_role_id']);
        $this->assertSame(['223456789012345678'], $result['removed_role_ids']);
        $this->assertTrue($result['nickname_changed']);
        $this->assertSame('[=ALPHA=] Speirs', $result['nickname']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/roles/323456789012345678')
            && $request->hasHeader('Authorization', 'Bot test-token'));

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/members/523456789012345678')
            && $request['nick'] === '[=ALPHA=] Speirs');
    }

    public function test_sqa_group_role_can_be_assigned_and_removed_without_lifecycle_role_configuration(): void
    {
        config()->set('newslot.procedures.discord.enabled', true);
        config()->set('newslot.procedures.discord.bot_token', 'test-token');
        config()->set('newslot.procedures.discord.base_url', 'https://discord.test/api/v10');

        $setting = new MemberProcedureSetting([
            'discord_guild_id' => '123456789012345678',
        ]);
        $user = new User([
            'nick' => 'Rylod',
            'discord_id' => '523456789012345678',
        ]);
        $memberGets = 0;

        Http::fake(function (Request $request) use (&$memberGets) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/members/523456789012345678')) {
                $memberGets++;

                return match ($memberGets) {
                    1 => Http::response(['roles' => [], 'nick' => 'Rylod'], 200),
                    2 => Http::response(['roles' => ['623456789012345678'], 'nick' => 'Rylod'], 200),
                    3 => Http::response(['roles' => ['623456789012345678'], 'nick' => 'Rylod'], 200),
                    default => Http::response(['roles' => [], 'nick' => 'Rylod'], 200),
                };
            }

            if ($request->method() === 'PUT' && str_ends_with($url, '/roles/623456789012345678')) {
                return Http::response([], 204);
            }

            if ($request->method() === 'DELETE' && str_ends_with($url, '/roles/623456789012345678')) {
                return Http::response([], 204);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $service = app(DiscordService::class);
        $added = $service->assignRole($user, $setting, '623456789012345678', 'Grupo SQA');
        $removed = $service->removeRole($user, $setting, '623456789012345678', 'Grupo SQA');

        $this->assertSame('623456789012345678', $added['added_role_id']);
        $this->assertSame(['623456789012345678'], $removed['removed_role_ids']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && str_ends_with($request->url(), '/roles/623456789012345678'));
        Http::assertSent(fn (Request $request): bool => $request->method() === 'DELETE'
            && str_ends_with($request->url(), '/roles/623456789012345678'));
    }

    public function test_departure_is_completed_when_member_is_already_absent(): void
    {
        $setting = $this->configure();
        $user = new User([
            'nick' => 'OldMember',
            'discord_id' => '623456789012345678',
        ]);

        Http::fake([
            'https://discord.test/api/v10/guilds/123456789012345678/members/623456789012345678' => Http::response([], 404),
        ]);

        $result = app(DiscordService::class)->removeManagedRoles($user, $setting, 'Baja');

        $this->assertSame('member_already_absent', $result['action']);
        $this->assertFalse($result['nickname_changed']);
    }

    public function test_cese_ban_is_idempotent(): void
    {
        $setting = $this->configure();
        $user = new User([
            'nick' => 'Dismissed',
            'discord_id' => '723456789012345678',
        ]);

        Http::fake(function (Request $request) {
            if ($request->method() === 'GET' && str_ends_with($request->url(), '/bans/723456789012345678')) {
                return Http::response(['message' => 'Unknown Ban'], 404);
            }

            if ($request->method() === 'PUT' && str_ends_with($request->url(), '/bans/723456789012345678')) {
                return Http::response([], 204);
            }

            return Http::response([], 500);
        });

        $result = app(DiscordService::class)->ban($user, $setting, 'Cese');

        $this->assertSame('banned', $result['action']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PUT'
            && $request['delete_message_seconds'] === 0);
    }

    public function test_bot_nickname_can_be_applied_from_filament_configuration(): void
    {
        $setting = $this->configure();

        Http::fake([
            'https://discord.test/api/v10/guilds/123456789012345678/members/@me' => Http::response([
                'nick' => 'NewSlot SQA',
                'roles' => [],
            ], 200),
        ]);

        $result = app(DiscordService::class)->applyBotNickname($setting);

        $this->assertSame('NewSlot SQA', $result['nickname']);
        Http::assertSent(fn (Request $request): bool => $request->method() === 'PATCH'
            && str_ends_with($request->url(), '/members/@me')
            && $request['nick'] === 'NewSlot SQA');
    }

    public function test_connection_requires_ban_permission_for_full_procedure_support(): void
    {
        $setting = $this->configure();

        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/users/@me')) {
                return Http::response(['id' => '923456789012345678', 'username' => 'Bot-SquadALPHA'], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/guilds/123456789012345678')) {
                return Http::response(['id' => '123456789012345678', 'name' => 'Squad ALPHA'], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/guilds/123456789012345678/roles')) {
                return Http::response([
                    ['id' => '123456789012345678', 'name' => '@everyone', 'position' => 0, 'permissions' => '0', 'managed' => false],
                    ['id' => '923456789012345679', 'name' => 'Bot', 'position' => 10, 'permissions' => (string) ((1 << 28) | (1 << 27) | (1 << 26) | (1 << 10) | (1 << 0)), 'managed' => false],
                    ['id' => '223456789012345678', 'name' => 'RECLUTA', 'position' => 3, 'permissions' => '0', 'managed' => false],
                    ['id' => '323456789012345678', 'name' => 'ALPHA', 'position' => 4, 'permissions' => '0', 'managed' => false],
                    ['id' => '423456789012345678', 'name' => 'RESERVA', 'position' => 5, 'permissions' => '0', 'managed' => false],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/guilds/123456789012345678/members/923456789012345678')) {
                return Http::response(['roles' => ['923456789012345679'], 'nick' => 'Bot-SquadALPHA'], 200);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Banear miembros');

        app(DiscordService::class)->testConnection($setting);
    }

    public function test_filament_catalog_loads_guilds_roles_and_invite_channels_from_bot(): void
    {
        $this->configure();
        Http::fake(function (Request $request) {
            $url = $request->url();

            if ($request->method() === 'GET' && str_ends_with($url, '/users/@me/guilds')) {
                return Http::response([
                    ['id' => '223456789012345679', 'name' => 'Servidor Z'],
                    ['id' => '123456789012345678', 'name' => 'Squad ALPHA'],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/guilds/123456789012345678/roles')) {
                return Http::response([
                    ['id' => '123456789012345678', 'name' => '@everyone', 'position' => 0, 'managed' => false],
                    ['id' => '923456789012345678', 'name' => 'Bot-SquadALPHA', 'position' => 10, 'managed' => true],
                    ['id' => '323456789012345678', 'name' => 'ALPHA', 'position' => 5, 'managed' => false],
                    ['id' => '223456789012345678', 'name' => 'RECLUTA', 'position' => 3, 'managed' => false],
                ], 200);
            }

            if ($request->method() === 'GET' && str_ends_with($url, '/guilds/123456789012345678/channels')) {
                return Http::response([
                    ['id' => '823456789012345678', 'name' => 'bienvenida', 'type' => 0, 'position' => 2],
                    ['id' => '823456789012345679', 'name' => 'General', 'type' => 4, 'position' => 1],
                    ['id' => '823456789012345680', 'name' => 'Sala voz', 'type' => 2, 'position' => 3],
                    ['id' => '823456789012345681', 'name' => 'hilo', 'type' => 11, 'position' => 4],
                ], 200);
            }

            return Http::response(['message' => 'Unexpected request'], 500);
        });

        $service = app(DiscordService::class);

        $this->assertSame([
            '223456789012345679' => 'Servidor Z',
            '123456789012345678' => 'Squad ALPHA',
        ], $service->guildOptions());

        $this->assertSame([
            '323456789012345678' => 'ALPHA',
            '223456789012345678' => 'RECLUTA',
        ], $service->roleOptions('123456789012345678'));

        $this->assertSame([
            '823456789012345678' => 'Texto · #bienvenida',
            '823456789012345680' => 'Voz · Sala voz',
        ], $service->channelOptions('123456789012345678'));
    }

}
