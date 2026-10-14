<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

/** Diagnostic only; never displays credentials, signing keys or personal data. */
class WikiOidcReadiness extends Command
{
    protected $signature = 'wiki:oidc-readiness';
    protected $description = 'Check readiness of the Wiki OIDC provider (read-only checks)';

    public function handle(): int
    {
        $issues = [];
        $issuer = (string) config('wiki_oidc.issuer');
        $url = (string) config('app.url');
        $this->line('OIDC enabled: '.(config('wiki_oidc.enabled') ? 'YES' : 'NO'));
        $this->line('Issuer: '.$issuer);
        $this->line('Application URL: '.$url);
        $this->line('Cache: '.config('cache.default'));
        $this->line('Session: '.config('session.driver'));
        if (! str_starts_with($issuer, 'https://')) $issues[] = 'OIDC issuer must use HTTPS';
        if (rtrim($url, '/') !== $issuer) $issues[] = 'APP_URL and OIDC issuer differ';
        if ((string) config('wiki_oidc.client_secret') === '') $issues[] = 'OIDC client secret missing';
        if (strlen((string) config('wiki_oidc.client_secret')) < 32) $issues[] = 'OIDC client secret too short';
        $private = (string) config('wiki_oidc.private_key_path');
        $public = (string) config('wiki_oidc.public_key_path');
        if (! is_readable($private)) $issues[] = 'Private signing key not readable';
        if (! is_readable($public)) $issues[] = 'Public verification key not readable';
        if (is_readable($private) && is_readable($public)) {
            $priv = @openssl_pkey_get_private(@file_get_contents($private));
            $pub = @openssl_pkey_get_public(@file_get_contents($public));
            if (! $priv || ! $pub || ! is_array($detail = openssl_pkey_get_details($priv)) ||
                ! isset($detail['key']) || trim($detail['key']) !== trim((string) @file_get_contents($public))) {
                $issues[] = 'RSA public/private key pair does not match';
            }
        }
        $driver = (string) config('cache.default');
        if (in_array($driver, ['array', 'null'], true)) $issues[] = 'Cache driver cannot provide persistent authorization code storage';
        try {
            $testKey = 'wiki_oidc:readiness:'.bin2hex(random_bytes(8));
            $lock = Cache::lock($testKey, 5);
            if (! $lock->get()) $issues[] = 'Cache locking unavailable';
            else $lock->release();
        } catch (\Throwable $e) {
            $issues[] = 'Cache locking unavailable: '.$e::class;
        }
        foreach ($issues as $issue) $this->error($issue);
        if ($issues) return self::FAILURE;
        $this->info('Provider prerequisites OK. This is NOT an OIDC integration or security test.');
        return self::SUCCESS;
    }
}
