<?php

namespace Tests\Unit;

use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\MemberProcedures\ArmaSquadsService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ArmaSquadsServiceTest extends TestCase
{
    public function test_missing_member_is_created_with_steam_id64_and_nick(): void
    {
        config()->set('newslot.procedures.armasquads.enabled', true);
        config()->set('newslot.procedures.armasquads.api_key', 'test-key');
        config()->set('newslot.procedures.armasquads.base_url', 'https://armasquads.test/api/v1');

        Http::fake([
            'https://armasquads.test/api/v1/squads/42/members/76561198000000000' => Http::response([], 404),
            'https://armasquads.test/api/v1/squads/42/members' => Http::response(['ok' => true], 201),
        ]);

        $user = new User(['nick' => 'Rylod', 'steam_id' => '76561198000000000']);
        $setting = new MemberProcedureSetting(['armasquads_squad_id' => '42']);

        $result = app(ArmaSquadsService::class)->upsert($user, $setting);

        $this->assertSame('created', $result['action']);

        Http::assertSent(fn ($request): bool => $request->method() === 'POST'
            && $request->url() === 'https://armasquads.test/api/v1/squads/42/members'
            && $request->hasHeader('X-API-Key', 'test-key')
            && $request['uuid'] === '76561198000000000'
            && $request['username'] === 'Rylod');
    }

    public function test_existing_member_with_changed_nick_is_updated(): void
    {
        config()->set('newslot.procedures.armasquads.enabled', true);
        config()->set('newslot.procedures.armasquads.api_key', 'test-key');
        config()->set('newslot.procedures.armasquads.base_url', 'https://armasquads.test/api/v1');

        Http::fake([
            'https://armasquads.test/api/v1/squads/42/members/76561198000000000' => Http::sequence()
                ->push(['uuid' => '76561198000000000', 'username' => 'OldNick'], 200)
                ->push(['ok' => true], 200),
        ]);

        $user = new User(['nick' => 'Rylod', 'steam_id' => '76561198000000000']);
        $setting = new MemberProcedureSetting(['armasquads_squad_id' => '42']);

        $result = app(ArmaSquadsService::class)->upsert($user, $setting);

        $this->assertSame('updated', $result['action']);
    }
}
