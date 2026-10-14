<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class WikiOidcKeyGenerate extends Command
{
    protected $signature = 'wiki:oidc-key-generate';
    protected $description = 'Generate RSA signing keys for Wiki OIDC, without overwriting existing keys';

    public function handle(): int
    {
        $privatePath = config('wiki_oidc.private_key_path');
        $publicPath = config('wiki_oidc.public_key_path');
        if (file_exists($privatePath) || file_exists($publicPath)) {
            $this->error('Refusing to overwrite existing OIDC keys.');
            return self::FAILURE;
        }
        $key = openssl_pkey_new(['private_key_type' => OPENSSL_KEYTYPE_RSA, 'private_key_bits' => 3072]);
        if (! $key || ! openssl_pkey_export($key, $private)) {
            $this->error('Cannot create RSA key pair.');
            return self::FAILURE;
        }
        $public = openssl_pkey_get_details($key)['key'] ?? null;
        if (! $public) return self::FAILURE;
        foreach ([$privatePath, $publicPath] as $path) {
            if (! is_dir(dirname($path)) && ! mkdir(dirname($path), 0700, true) && ! is_dir(dirname($path))) {
                $this->error('Cannot create key directory.');
                return self::FAILURE;
            }
        }
        if (file_put_contents($privatePath, $private, LOCK_EX) === false || file_put_contents($publicPath, $public, LOCK_EX) === false) {
            $this->error('Could not write keys.');
            return self::FAILURE;
        }
        @chmod($privatePath, 0600);
        @chmod($publicPath, 0644);
        $this->info('OIDC signing keys created. Back up securely; do not commit private.pem to Git.');
        return self::SUCCESS;
    }
}
