<?php

namespace App\Services\Wiki;

use RuntimeException;

/** RS256 OIDC ID tokens, signed with OpenSSL; private key never leaves Laravel. */
class WikiOidcJwt
{
    public function encode(array $claims): string
    {
        $header = ['alg' => 'RS256', 'typ' => 'JWT', 'kid' => config('wiki_oidc.key_id')];
        $input = self::b64(json_encode($header, JSON_THROW_ON_ERROR)).'.'.self::b64(json_encode($claims, JSON_THROW_ON_ERROR));
        $private = @openssl_pkey_get_private(@file_get_contents(config('wiki_oidc.private_key_path')) ?: '');
        if ($private === false || ! openssl_sign($input, $signature, $private, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Wiki OIDC private signing key is unavailable');
        }
        return $input.'.'.self::b64($signature);
    }

    public function jwk(): array
    {
        $key = @openssl_pkey_get_public(@file_get_contents(config('wiki_oidc.public_key_path')) ?: '');
        $details = $key === false ? false : openssl_pkey_get_details($key);
        if (! is_array($details) || ! isset($details['rsa']['n'], $details['rsa']['e'])) {
            throw new RuntimeException('Wiki OIDC public verification key is unavailable');
        }
        return ['kty' => 'RSA', 'use' => 'sig', 'alg' => 'RS256', 'kid' => config('wiki_oidc.key_id'), 'n' => self::b64($details['rsa']['n']), 'e' => self::b64($details['rsa']['e'])];
    }

    public static function b64(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
