<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use App\Services\Wiki\WikiOidcJwt;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/** Operator-assisted, short-lived proof of the SQA account password. */
class WikiIssueLinkProof extends Command
{
    protected $signature = 'wiki:issue-link-proof {user_id : SQA user ID}';
    protected $description = 'Issue a five-minute RSA-signed linking proof after checking the SQA password (does not change users)';

    public function handle(WikiAccessPolicy $policy, WikiOidcJwt $jwt): int
    {
        $user = User::find($this->argument('user_id'));
        if (! $user || ! $policy->canAuthenticate($user) || $policy->isSuperAdmin($user)) {
            $this->error('Account not eligible for ordinary linking (protected admin requires separate migration).');
            return self::FAILURE;
        }
        if (! filter_var($user->email, FILTER_VALIDATE_EMAIL)) {
            $this->error('Invalid user email.');
            return self::FAILURE;
        }
        $password = $this->secret('Confirm the SQA account password (input hidden)');
        if (! is_string($password) || $password === '' || ! Hash::check($password, $user->password)) {
            $this->error('Identity verification failed.');
            return self::FAILURE;
        }
        unset($password);
        $now = time();
        try {
            $token = $jwt->encode([
                'iss' => rtrim((string) config('wiki_oidc.issuer'), '/'),
                'aud' => 'sqa-bookstack-manual-link-v1',
                'sub' => 'sqa-user-'.$user->getKey(),
                'email' => mb_strtolower(trim((string) $user->email)),
                'iat' => $now,
                'nbf' => $now,
                'exp' => $now + 300,
                'jti' => bin2hex(random_bytes(16)),
            ]);
        } catch (\Throwable $e) {
            $this->error('Signing key unavailable. Configure RSA keys without committing them.');
            return self::FAILURE;
        }
        $this->warn('Sensitive, bearer proof valid for FIVE MINUTES. Do not place in logs or chat.');
        $this->line($token);
        return self::SUCCESS;
    }
}
