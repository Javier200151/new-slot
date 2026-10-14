<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use Illuminate\Console\Command;

/** Read-only smoke test: does not print emails, secrets or passwords. */
class WikiOidcPreview extends Command
{
    protected $signature = 'wiki:oidc-preview {user_id : ID of the Squad ALPHA user}';
    protected $description = 'Preview BookStack OIDC authorization and SQA-derived groups, without changing data';

    public function handle(WikiAccessPolicy $policy): int
    {
        $id = filter_var($this->argument('user_id'), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if (! $id) {
            $this->error('Provide a positive integer user ID.');
            return self::FAILURE;
        }
        $user = User::find($id);
        if (! $user) {
            $this->error('User not found.');
            return self::FAILURE;
        }
        $this->line('ID: '.$user->getKey());
        $this->line('Email verified: '.($user->hasVerifiedEmail() ? 'yes' : 'no'));
        $this->line('Status: '.((string) ($user->status?->name ?? 'none')));
        $this->line('Protected administrator: '.($policy->isSuperAdmin($user) ? 'yes' : 'no'));
        $this->line('SSO authorized: '.($policy->canAuthenticate($user) ? 'yes' : 'no'));
        $this->line('Proposed OIDC groups: '.json_encode($policy->oidcGroups($user), JSON_UNESCAPED_UNICODE));
        $this->warn('Preview only. OIDC endpoints, account linking and BookStack sync are not enabled.');
        return self::SUCCESS;
    }
}
