<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Projects the documented first RECLUTA -> ACTIVO promotion into the visible
 * status history. This is a historical annotation, not a live status change.
 * It must never affect veteranía calculations, current status or recruitment.
 */
class FirstMembershipHistoryService
{
    public const SOURCE = 'documented_membership';

    public function syncUser(User $user): void
    {
        $this->sync(
            (int) $user->id,
            $user->member_at?->toDateString(),
            $user->recruited_at?->toDateString(),
        );
    }

    public function sync(int $userId, ?string $memberAt, ?string $recruitedAt = null): void
    {
        if (! Schema::hasTable('user_status_histories') || ! Schema::hasTable('status')) {
            return;
        }

        $hash = hash('sha256', self::SOURCE . '|' . $userId);
        $row = DB::table('user_status_histories')->where('source_hash', $hash)->first();

        // Only delete our own derived row when the underlying date is removed
        // or demonstrably inconsistent. Never alter genuine status changes.
        if (
            ! $memberAt
            || ($recruitedAt !== null && $recruitedAt > $memberAt)
        ) {
            $this->deleteDerivedRow($row);
            return;
        }

        $from = DB::table('status')->whereRaw('UPPER(TRIM(name)) = ?', ['RECLUTA'])->value('id');
        $to = DB::table('status')->whereRaw('UPPER(TRIM(name)) = ?', ['ACTIVO'])->value('id');
        if (! $from || ! $to) {
            return;
        }

        // An ACTIVO transition already recorded that day is stronger evidence.
        // Do not invent a second or contradictory RECLUTA -> ACTIVO event.
        // Baselines are snapshots, not actual transitions.
        $realPromotionRecorded = DB::table('user_status_histories')
            ->where('user_id', $userId)
            ->where('to_status_id', $to)
            ->whereDate('changed_at', $memberAt)
            ->whereNotIn('source', ['baseline', self::SOURCE])
            ->exists();

        if ($realPromotionRecorded) {
            $this->deleteDerivedRow($row);
            return;
        }

        $values = [
            'from_status_id' => $from,
            'to_status_id' => $to,
            'changed_at' => $memberAt . ' 00:00:00',
            'changed_by_user_id' => null,
            'retutored_by' => null,
            'note' => 'Primera promoción a miembro documentada mediante «Miembro desde».',
            'updated_at' => now(),
        ];

        if ($row) {
            if ($row->source === self::SOURCE) {
                DB::table('user_status_histories')->where('id', $row->id)->update($values);
            }
            return;
        }

        DB::table('user_status_histories')->insertOrIgnore([
            'user_id' => $userId,
            'source' => self::SOURCE,
            'source_hash' => $hash,
            'created_at' => now(),
            ...$values,
        ]);
    }

    private function deleteDerivedRow(?object $row): void
    {
        if ($row && $row->source === self::SOURCE) {
            DB::table('user_status_histories')->where('id', $row->id)->delete();
        }
    }
}
