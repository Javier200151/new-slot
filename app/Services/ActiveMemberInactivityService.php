<?php

namespace App\Services;

use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ActiveMemberInactivityService
{
    /** @return array<string, string> */
    public static function periods(): array
    {
        return [
            'top5' => '5 con más tiempo',
            '1m' => '1 mes',
            '2m' => '2 meses',
            '3m' => '3 meses',
            '6m' => '6 meses',
            '1y' => '1 año',
        ];
    }

    /**
     * Usa las mismas reglas de participación que el historial de reclutas:
     * asignación actual o última anotación histórica asignada/movida,
     * siempre sobre un evento ya celebrado y no cancelado.
     *
     * @return array{members:array<int, array{user:User,days:int,last_event_at:?CarbonImmutable,reference_at:CarbonImmutable}>,total:int}
     */
    public function list(string $period = 'top5'): array
    {
        $period = array_key_exists($period, self::periods()) ? $period : 'top5';
        $now = CarbonImmutable::now();

        // Último movimiento por usuario y evento; un desapunte posterior anula
        // una asignación histórica, igual que en RecruitmentParticipationService.
        $rankedHistory = DB::table('event_slot_history')
            ->whereNotNull('user_id')
            ->whereNotNull('event_id')
            ->select(['user_id', 'event_id', 'action', 'to_slot_key'])
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY user_id, event_id ORDER BY created_at DESC, id DESC) AS movement_order');

        $historicalAssignments = DB::query()
            ->fromSub($rankedHistory, 'last_movement')
            ->where('movement_order', 1)
            ->whereIn('action', ['assigned', 'moved'])
            ->whereNotNull('to_slot_key')
            ->where('to_slot_key', '<>', '')
            ->select(['user_id', 'event_id']);

        $currentAssignments = DB::table('event_slots')
            ->whereNotNull('user_id')
            ->select(['user_id', 'event_id']);

        $assignments = $currentAssignments->union($historicalAssignments);

        $lastEvents = DB::query()
            ->fromSub($assignments, 'participation')
            ->join('events', 'events.id', '=', 'participation.event_id')
            ->leftJoin('event_status', 'event_status.id', '=', 'events.event_status_id')
            ->whereNull('events.deleted_at')
            ->where('events.date', '<=', $now)
            ->where(function ($query): void {
                $query->whereNull('event_status.id')
                    ->orWhereRaw('UPPER(TRIM(event_status.name)) <> ?', ['CANCELADO']);
            })
            ->groupBy('participation.user_id')
            ->select('participation.user_id')
            ->selectRaw('MAX(events.date) AS last_event_at');

        $users = User::query()
            ->select(['users.id', 'users.nick', 'users.member_at', 'users.created_at'])
            ->whereHas('status', fn ($query) => $query->whereRaw('UPPER(TRIM(name)) = ?', ['ACTIVO']))
            // PlanaMayor es una cuenta técnica de superadministración: no participa en eventos.
            ->whereRaw('LOWER(TRIM(users.nick)) <> ?', ['planamayor'])
            ->leftJoinSub($lastEvents, 'last_participation', 'last_participation.user_id', '=', 'users.id')
            ->addSelect('last_participation.last_event_at')
            ->get();

        $threshold = match ($period) {
            '1m' => $now->subMonth(),
            '2m' => $now->subMonths(2),
            '3m' => $now->subMonths(3),
            '6m' => $now->subMonths(6),
            '1y' => $now->subYear(),
            default => null,
        };

        $members = $users->map(function (User $user) use ($now): array {
            $lastEvent = filled($user->last_event_at)
                ? CarbonImmutable::parse($user->last_event_at)
                : null;
            // Sin eventos registrados, cuenta desde que ingresó como ACTIVO.
            $reference = $lastEvent
                ?? ($user->member_at
                    ? CarbonImmutable::parse($user->member_at)
                    : CarbonImmutable::parse($user->created_at ?? $now));
            $reference = $reference->isAfter($now) ? $now : $reference;

            return [
                'user' => $user,
                'days' => (int) $reference->diffInDays($now),
                'last_event_at' => $lastEvent,
                'reference_at' => $reference,
            ];
        })
            ->filter(fn (array $item): bool => $threshold === null || ! $item['reference_at']->isAfter($threshold))
            ->sort(function (array $a, array $b): int {
                return ($b['days'] <=> $a['days'])
                    ?: strcasecmp((string) $a['user']->nick, (string) $b['user']->nick);
            })
            ->values();

        $total = $members->count();

        return [
            'members' => ($period === 'top5' ? $members->take(5) : $members)->all(),
            'total' => $total,
        ];
    }
}
