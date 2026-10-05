<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
use App\Models\ProcedureNotification;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

class ProcedureNotificationService
{
    public function toGroup(MemberProcedure $procedure, MemberProcedureStep $step, int $groupId, string $title, string $body): ProcedureNotification
    {
        return $this->store($procedure, $step, ProcedureNotification::TARGET_GROUP, $groupId, $title, $body);
    }

    public function toUser(MemberProcedure $procedure, MemberProcedureStep $step, int $userId, string $title, string $body): ProcedureNotification
    {
        return $this->store($procedure, $step, ProcedureNotification::TARGET_USER, $userId, $title, $body);
    }

    public function visibleTo(User $user): Builder
    {
        $groupIds = $user->sqaGroups()->pluck('sqa_groups.id')->map(fn ($id): int => (int) $id)->all();

        return ProcedureNotification::query()
            ->whereNull('acknowledged_at')
            ->where(function (Builder $query) use ($user, $groupIds): void {
                $query->where(function (Builder $query) use ($user): void {
                    $query->where('target_type', ProcedureNotification::TARGET_USER)
                        ->where('target_id', $user->id);
                });

                if ($groupIds !== []) {
                    $query->orWhere(function (Builder $query) use ($groupIds): void {
                        $query->where('target_type', ProcedureNotification::TARGET_GROUP)
                            ->whereIn('target_id', $groupIds);
                    });
                }
            });
    }

    public function acknowledgeFor(User $user, int $notificationId): void
    {
        $notification = $this->visibleTo($user)->whereKey($notificationId)->firstOrFail();
        $notification->update([
            'acknowledged_at' => now(),
            'acknowledged_by_user_id' => $user->id,
        ]);
    }

    private function store(MemberProcedure $procedure, MemberProcedureStep $step, string $targetType, int $targetId, string $title, string $body): ProcedureNotification
    {
        $key = implode(':', ['procedure', $procedure->id, $step->step_key, $targetType, $targetId]);

        return ProcedureNotification::query()->firstOrCreate(
            ['dedupe_key' => $key],
            [
                'member_procedure_id' => $procedure->id,
                'member_procedure_step_id' => $step->id,
                'target_type' => $targetType,
                'target_id' => $targetId,
                'title' => $title,
                'body' => $body,
            ],
        );
    }
}
