<?php

namespace App\Services\LinkedAccounts;

use Illuminate\Support\Facades\Http;
use RuntimeException;

class SteamAccountLinkService
{
    private const SELECT_IDENTIFIER = 'http://specs.openid.net/auth/2.0/identifier_select';

    public function authorizationUrl(string $returnTo): string
    {
        $endpoint = rtrim((string) config('services.steam_openid.endpoint', 'https://steamcommunity.com/openid/login'), '/');
        $realm = $this->realmFromReturnTo($returnTo);

        return $endpoint . '?' . http_build_query([
            'openid.ns' => 'http://specs.openid.net/auth/2.0',
            'openid.mode' => 'checkid_setup',
            'openid.return_to' => $returnTo,
            'openid.realm' => $realm,
            'openid.identity' => self::SELECT_IDENTIFIER,
            'openid.claimed_id' => self::SELECT_IDENTIFIER,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array{id:string, profile_url:string}
     */
    public function resolveIdentity(array $query, string $expectedReturnTo): array
    {
        $openid = $this->normalizeOpenIdParameters($query);
        $mode = trim((string) ($openid['openid.mode'] ?? ''));

        if ($mode === 'cancel') {
            throw new RuntimeException('Has cancelado la vinculación con Steam.');
        }

        if ($mode !== 'id_res') {
            throw new RuntimeException('Steam devolvió una respuesta OpenID no válida.');
        }

        if (trim((string) ($openid['openid.ns'] ?? '')) !== 'http://specs.openid.net/auth/2.0') {
            throw new RuntimeException('Steam devolvió un namespace OpenID inesperado.');
        }

        $signedFields = array_filter(array_map(
            'trim',
            explode(',', (string) ($openid['openid.signed'] ?? '')),
        ));

        foreach (['op_endpoint', 'claimed_id', 'identity', 'return_to', 'response_nonce', 'assoc_handle'] as $requiredField) {
            if (! in_array($requiredField, $signedFields, true)) {
                throw new RuntimeException('La respuesta de Steam no firma todos los campos de identidad requeridos.');
            }
        }

        $returnTo = trim((string) ($openid['openid.return_to'] ?? ''));
        if ($returnTo === '' || ! hash_equals($expectedReturnTo, $returnTo)) {
            throw new RuntimeException('La respuesta de Steam no coincide con esta solicitud de vinculación.');
        }

        $opEndpoint = rtrim((string) ($openid['openid.op_endpoint'] ?? ''), '/');
        $expectedEndpoint = rtrim((string) config('services.steam_openid.endpoint', 'https://steamcommunity.com/openid/login'), '/');

        if ($opEndpoint !== $expectedEndpoint) {
            throw new RuntimeException('Steam devolvió un proveedor OpenID inesperado.');
        }

        $claimedId = trim((string) ($openid['openid.claimed_id'] ?? ''));
        $identity = trim((string) ($openid['openid.identity'] ?? ''));

        if ($claimedId === '' || $identity === '' || ! hash_equals($claimedId, $identity)) {
            throw new RuntimeException('Steam devolvió una identidad OpenID inconsistente.');
        }

        if (preg_match('#^https?://steamcommunity\.com/openid/id/(\d{17})$#', $claimedId, $matches) !== 1) {
            throw new RuntimeException('Steam no devolvió un SteamID64 válido.');
        }

        $verificationPayload = $openid;
        $verificationPayload['openid.mode'] = 'check_authentication';

        $endpoint = rtrim((string) config('services.steam_openid.endpoint', 'https://steamcommunity.com/openid/login'), '/');
        $timeout = max(3, (int) config('services.steam_openid.timeout', 10));

        $verification = Http::asForm()
            ->timeout($timeout)
            ->post($endpoint, $verificationPayload);

        if (! $verification->successful()
            || preg_match('/(?:^|\r?\n)is_valid:true(?:\r?\n|$)/', trim($verification->body())) !== 1) {
            throw new RuntimeException('Steam no pudo verificar criptográficamente la respuesta OpenID.');
        }

        $steamId = $matches[1];

        return [
            'id' => $steamId,
            'profile_url' => 'https://steamcommunity.com/profiles/' . $steamId,
        ];
    }

    /**
     * PHP convierte los puntos de los nombres de query string en guiones bajos.
     * Reconstruimos los nombres OpenID originales antes de verificarlos con Steam.
     *
     * @param  array<string, mixed>  $query
     * @return array<string, string>
     */
    private function normalizeOpenIdParameters(array $query): array
    {
        $openid = [];

        foreach ($query as $key => $value) {
            if (! is_scalar($value) || ! str_starts_with((string) $key, 'openid_')) {
                continue;
            }

            $openid['openid.' . substr((string) $key, 7)] = (string) $value;
        }

        return $openid;
    }

    private function realmFromReturnTo(string $returnTo): string
    {
        $parts = parse_url($returnTo);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            throw new RuntimeException('No se pudo construir el realm de Steam OpenID.');
        }

        $realm = $parts['scheme'] . '://' . $parts['host'];

        if (isset($parts['port'])) {
            $realm .= ':' . $parts['port'];
        }

        return rtrim($realm, '/') . '/';
    }
}
