<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Services\Wiki\WikiAccessPolicy;
use Illuminate\Console\Command;

/** Read-only, privacy-preserving comparison of BookStack account records. */
class WikiBookstackLinkAudit extends Command
{
    protected $signature = 'wiki:bookstack-link-audit {file : Local JSON inventory exported on the BookStack host} {--details : Show BookStack and SQA numeric IDs without emails}';
    protected $description = 'Audit possible BookStack account links without changing either database';

    public function handle(WikiAccessPolicy $policy): int
    {
        $path = (string) $this->argument('file');
        if (! is_file($path) || ! is_readable($path) || filesize($path) > 5 * 1024 * 1024) {
            $this->error('Readable JSON file <= 5 MiB required.');
            return self::FAILURE;
        }
        try {
            $rows = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);
        } catch (\JsonException $e) {
            $this->error('Invalid JSON inventory.');
            return self::FAILURE;
        }
        if (! is_array($rows) || ! array_is_list($rows) || count($rows) > 10000) {
            $this->error('Expected a JSON array of at most 10000 users.');
            return self::FAILURE;
        }

        $emails = [];
        foreach ($rows as $item) {
            if (! is_array($item) || ! isset($item['id'], $item['email'], $item['name']) || ! is_numeric($item['id']) || ! is_string($item['email']) || ! is_string($item['name'])) {
                $this->error('Invalid user record in JSON.');
                return self::FAILURE;
            }
            $email = mb_strtolower(trim($item['email']));
            if ($email !== '') $emails[$email] = true;
        }
        $byEmail = [];
        User::withTrashed()->whereNotNull('email')->select(['id', 'email', 'email_verified_at', 'status_id', 'deleted_at', 'is_protected_admin'])
            ->with('status')->chunkById(200, function ($users) use (&$byEmail, $emails) {
                foreach ($users as $user) {
                    $email = mb_strtolower(trim((string) $user->email));
                    if (isset($emails[$email])) $byEmail[$email][] = $user;
                }
            });

        $counts = ['special' => 0, 'already_linked' => 0, 'verified_candidate' => 0, 'not_verified' => 0, 'not_authorized' => 0, 'no_match' => 0, 'ambiguous' => 0];
        $seenIds = [];
        foreach ($rows as $item) {
            $id = (int) $item['id'];
            $email = mb_strtolower(trim($item['email']));
            $name = mb_strtolower(trim($item['name']));
            $ext = trim((string) ($item['external_auth_id'] ?? ''));
            $sqaId = null;
            if (isset($seenIds[$id])) {
                $classification = 'ambiguous';
            } elseif (in_array($id, [1, 2, 40, 41], true) || in_array($name, ['admin', 'guest', 'api', 'miembro sqa'], true)) {
                $classification = 'special';
            } elseif ($ext !== '') {
                $classification = 'already_linked';
            } else {
                $matches = $byEmail[$email] ?? [];
                if (!$email || count($matches) === 0) $classification = 'no_match';
                elseif (count($matches) > 1) $classification = 'ambiguous';
                else {
                    $user = $matches[0];
                    $sqaId = $user->getKey();
                    if (!$user->hasVerifiedEmail()) $classification = 'not_verified';
                    elseif (!$policy->canAuthenticate($user)) $classification = 'not_authorized';
                    else $classification = 'verified_candidate';
                }
            }
            $seenIds[$id] = true;
            $counts[$classification]++;
            if ($this->option('details')) {
                $this->line('BookStack ID '.$id.' -> '.($sqaId ? 'SQA ID '.$sqaId : '-').' : '.$classification);
            }
        }
        foreach ($counts as $type => $count) $this->line($type.': '.$count);
        $this->warn('READ ONLY: matching emails never proves ownership. No accounts have been linked.');
        $this->warn('Verified candidates require manual proof of the existing BookStack password before writing external_auth_id.');
        return self::SUCCESS;
    }
}
