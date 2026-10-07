<?php

namespace Tests\Unit;

use App\Services\LinkedAccounts\DiscordAccountLinkService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class DiscordAccountLinkServiceTest extends TestCase
{
    public function test_authorization_url_uses_code_flow_state_and_identify_scope(): void
    {
        config()->set('services.discord_oauth.client_id', '123456789012345678');

        $url = app(DiscordAccountLinkService::class)->authorizationUrl(
            'secure-state',
            'https://newslot.test/perfil/cuentas/discord/callback',
        );

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('code', $query['response_type']);
        $this->assertSame('123456789012345678', $query['client_id']);
        $this->assertSame('identify', $query['scope']);
        $this->assertSame('secure-state', $query['state']);
        $this->assertSame('https://newslot.test/perfil/cuentas/discord/callback', $query['redirect_uri']);
    }

    public function test_resolve_identity_exchanges_code_and_reads_current_user(): void
    {
        config()->set('services.discord_oauth.client_id', '123456789012345678');
        config()->set('services.discord_oauth.client_secret', 'secret');
        config()->set('services.discord_oauth.api_base_url', 'https://discord.test/api/v10');
        config()->set('services.discord_oauth.timeout', 10);

        Http::fake([
            'https://discord.test/api/v10/oauth2/token' => Http::response([
                'access_token' => 'user-access-token',
                'token_type' => 'Bearer',
                'scope' => 'identify',
            ], 200),
            'https://discord.test/api/v10/users/@me' => Http::response([
                'id' => '523456789012345678',
                'username' => 'rylod',
            ], 200),
        ]);

        $identity = app(DiscordAccountLinkService::class)->resolveIdentity(
            'authorization-code',
            'https://newslot.test/perfil/cuentas/discord/callback',
        );

        $this->assertSame('523456789012345678', $identity['id']);
        $this->assertSame('rylod', $identity['username']);

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://discord.test/api/v10/oauth2/token'
            && $request['grant_type'] === 'authorization_code'
            && $request['code'] === 'authorization-code');

        Http::assertSent(fn (Request $request): bool => $request->method() === 'GET'
            && $request->url() === 'https://discord.test/api/v10/users/@me'
            && $request->hasHeader('Authorization', 'Bearer user-access-token'));
    }
}
