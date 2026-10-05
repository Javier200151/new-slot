<?php

namespace App\Services;

use App\Models\Status;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VeterancyStatusHistoryService
{
    public function handleTransition(User $user, int $previousStatusId): void
    {
        $currentStatusId = (int) $user->status_id;
        if ($previousStatusId === $currentStatusId || $currentStatusId <= 0) {
            return;
        }

        $previousName = $this->statusName($previousStatusId);
        $currentName = $this->statusName($currentStatusId);

        if (
            $previousName === 'RECLUTA'
            && $currentName === 'ACTIVO'
            && $user->member_at === null
        ) {
            app(ProtectedAdminGuard::class)->authorize($user);

            $memberAt = today()->toDateString();

            DB::table('users')
                ->where('id', $user->id)
                ->whereNull('member_at')
                ->update(['member_at' => $memberAt]);

            // Actualizamos también la instancia que sigue recorriendo los
            // observers, sin lanzar un segundo ciclo de eventos de User.
            $user->setAttribute('member_at', $memberAt);
        }

        if (! Schema::hasTable('user_status_histories')) {
            return;
        }

        UserStatusHistory::create([
            'user_id' => $user->id,
            'from_status_id' => $previousStatusId > 0 ? $previousStatusId : null,
            'to_status_id' => $currentStatusId,
            'changed_at' => now(),
            'changed_by_user_id' => Auth::id() ?: $user->updated_by,
            'source' => 'automatic',
        ]);
    }

    private function statusName(int $statusId): ?string
    {
        if ($statusId <= 0) {
            return null;
        }

        $name = Status::withTrashed()->whereKey($statusId)->value('name');

        return $name !== null ? strtoupper(trim((string) $name)) : null;
    }
}
