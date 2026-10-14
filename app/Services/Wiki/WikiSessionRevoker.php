<?php

namespace App\Services\Wiki;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

class WikiSessionRevoker
{
    public function record(User $user): void
    {
        if (!config('wiki_link.session_revoke_enabled', false) || $user->isProtectedAdmin()) return;
        $now = now();
        DB::table('wiki_session_revocations')->upsert([[
            'user_id' => $user->getKey(), 'subject' => 'sqa-user-'.$user->getKey(),
            'email' => mb_strtolower(trim((string) $user->email)),
            'attempts' => 0, 'next_attempt_at' => $now,
            'completed_at' => null, 'last_error' => null,
            'created_at' => $now, 'updated_at' => $now,
        ]], ['user_id'], ['subject','email','attempts','next_attempt_at','completed_at','last_error','updated_at']);
        // Best-effort immediate delivery; pending entry survives failures.
        $this->process((int)$user->getKey());
    }

    public function process(?int $userId = null): array
    {
        if (!config('wiki_link.session_revoke_enabled', false)) return ['processed'=>0,'pending'=>0];
        $query = DB::table('wiki_session_revocations')->whereNull('completed_at')
            ->where(function ($q) { $q->whereNull('next_attempt_at')->orWhere('next_attempt_at','<=',now()); });
        if ($userId !== null) $query->where('user_id',$userId);
        $rows = $query->orderBy('id')->limit(50)->get();
        $processed=0;
        foreach ($rows as $row) {
            try {
                // Ignore stale pending revocations after a member returns to an eligible state.
                $current = User::find($row->user_id);
                if ($current && app(WikiAccessPolicy::class)->canAuthenticate($current)) {
                    DB::table('wiki_session_revocations')->where('id',$row->id)->whereNull('completed_at')
                        ->update(['completed_at'=>now(),'last_error'=>null,'updated_at'=>now()]);
                    continue;
                }
                $result = app(WikiLinkClient::class)->call('revoke', $row->subject, $row->email);
                if (!in_array($result['status'] ?? null, ['revoked','not_linked'], true)) {
                    throw new \RuntimeException('Unexpected Wiki revoke response');
                }
                DB::table('wiki_session_revocations')->where('id',$row->id)
                    ->whereNull('completed_at')->update([
                        'completed_at'=>now(), 'last_error'=>null,'updated_at'=>now(),
                    ]);
                $processed++;
            } catch (Throwable $e) {
                $attempts=(int)$row->attempts + 1;
                DB::table('wiki_session_revocations')->where('id',$row->id)->whereNull('completed_at')->update([
                    'attempts'=>$attempts,
                    'next_attempt_at'=>now()->addSeconds(min(3600, 30 * (2 ** min(7,$attempts)))),
                    'last_error'=>mb_substr(get_class($e),0,200),
                    'updated_at'=>now(),
                ]);
                Log::warning('Wiki session revocation pending', ['user_id'=>$row->user_id,'exception'=>get_class($e)]);
            }
        }
        return ['processed'=>$processed,'pending'=>DB::table('wiki_session_revocations')->whereNull('completed_at')->count()];
    }
}
