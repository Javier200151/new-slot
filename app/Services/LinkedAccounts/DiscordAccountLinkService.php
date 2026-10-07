<?php

namespace App\Services\LinkedAccounts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class DiscordAccountLinkService
{
    public function isConfigured(): bool
    {
        return filled(config('services.discord_oauth.client_id'))
            && filled(config('services.discord_oauth.client_secret'));
    }

    public function authorizationUrl(string $state, string $redirectUri): string
    {
        $clientId = trim((string) config('services.discord_oauth.client_id'));

        if ($clientId === '') {
            throw new RuntimeException('Falta DISCORD_OAUTH_CLIENT_ID en el entorno del servidor.');
        }

        return 'https://discord.com/oauth2/authorize?' . http_build_query([
            'response_type' => 'code',
            'client_id' => $clientId,
            'scope' => 'identify',
            'state' => $state,
            'redirect_uri' => $redirectUri,
            'prompt' => 'consent',
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @return array{id:string, username:string}
     */
    public function resolveIdentity(string $code, string $redirectUri): array
    {
        $clientId = trim((string) config('services.discord_oauth.client_id'));
        $clientSecret = trim((string) config('services.discord_oauth.client_secret'));

        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('La vinculación OAuth2 de Discord no está configurada.');
        }

        $timeout = max(3, (int) config('services.discord_oauth.timeout', 10));
        $apiBaseUrl = rtrim((string) config('services.discord_oauth.api_base_url', 'https://discord.com/api/v10'), '/');

        $tokenResponse = Http::asForm()
            ->acceptJson()
            ->timeout($timeout)
            ->post($apiBaseUrl . '/oauth2/token', [
                'client_id' => $clientId,
                'client_secret' => $clientSecret,
                'grant_type' => 'authorization_code',
                'code' => $code,
                'redirect_uri' => $redirectUri,
            ]);

        if (! $tokenResponse->successful()) {
            throw new RuntimeException('Discord no pudo completar la autorización de la cuenta.');
        }

        $accessToken = trim((string) $tokenResponse->json('access_token'));
        $scopes = preg_split('/\s+/', trim((string) $tokenResponse->json('scope')), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        if ($accessToken === '' || ! in_array('identify', $scopes, true)) {
            throw new RuntimeException('Discord no devolvió una autorización válida para identificar al usuario.');
        }

        $userResponse = Http::withToken($accessToken)
            ->acceptJson()
            ->timeout($timeout)
            ->get($apiBaseUrl . '/users/@me');

        if (! $userResponse->successful()) {
            throw new RuntimeException('No se pudo obtener la identidad autorizada desde Discord.');
        }

        $id = trim((string) $userResponse->json('id'));
        $username = trim((string) $userResponse->json('username'));

        if (preg_match('/^\d{17,20}$/', $id) !== 1 || $username === '') {
            throw new RuntimeException('Discord devolvió una identidad incompleta o no válida.');
        }

        return [
            'id' => $id,
            'username' => $username,
        ];
    }
}
