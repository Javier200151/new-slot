<?php

namespace Tests\Unit;

use App\Services\LinkedAccounts\SteamAccountLinkService;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SteamAccountLinkServiceTest extends TestCase
{
    public function test_authorization_url_uses_official_openid_2_parameters(): void
    {
        config()->set('services.steam_openid.endpoint', 'https://steamcommunity.com/openid/');

        $returnTo = 'https://newslot.test/perfil/cuentas/steam/callback?state=secure-state';
        $url = app(SteamAccountLinkService::class)->authorizationUrl($returnTo);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame('http://specs.openid.net/auth/2.0', $query['openid_ns']);
        $this->assertSame('checkid_setup', $query['openid_mode']);
        $this->assertSame($returnTo, $query['openid_return_to']);
        $this->assertSame('https://newslot.test/', $query['openid_realm']);
        $this->assertSame('http://specs.openid.net/auth/2.0/identifier_select', $query['openid_identity']);
        $this->assertSame('http://specs.openid.net/auth/2.0/identifier_select', $query['openid_claimed_id']);
    }

    public function test_resolve_identity_verifies_response_with_steam_and_returns_steam_id64(): void
    {
        config()->set('services.steam_openid.endpoint', 'https://steamcommunity.com/openid/');
        config()->set('services.steam_openid.timeout', 10);

        $returnTo = 'https://newslot.test/perfil/cuentas/steam/callback?state=secure-state';

        Http::fake([
            'https://steamcommunity.com/openid/' => Http::response(
                "ns:http://specs.openid.net/auth/2.0\nis_valid:true\n",
                200,
            ),
        ]);

        $identity = app(SteamAccountLinkService::class)->resolveIdentity([
            'state' => 'secure-state',
            'openid_ns' => 'http://specs.openid.net/auth/2.0',
            'openid_mode' => 'id_res',
            'openid_op_endpoint' => 'https://steamcommunity.com/openid/login',
            'openid_claimed_id' => 'https://steamcommunity.com/openid/id/76561198000000000',
            'openid_identity' => 'https://steamcommunity.com/openid/id/76561198000000000',
            'openid_return_to' => $returnTo,
            'openid_response_nonce' => '2026-10-07T08:00:00Znonce',
            'openid_assoc_handle' => 'assoc',
            'openid_signed' => 'op_endpoint,claimed_id,identity,return_to,response_nonce,assoc_handle',
            'openid_sig' => 'signature',
        ], $returnTo);

        $this->assertSame('76561198000000000', $identity['id']);
        $this->assertSame(
            'https://steamcommunity.com/profiles/76561198000000000',
            $identity['profile_url'],
        );

        Http::assertSent(fn (Request $request): bool => $request->method() === 'POST'
            && $request->url() === 'https://steamcommunity.com/openid/'
            && $request['openid.mode'] === 'check_authentication'
            && $request['openid.claimed_id'] === 'https://steamcommunity.com/openid/id/76561198000000000');
    }
}
