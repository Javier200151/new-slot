<?php

namespace App\Services;

use App\Models\Status;
use App\Models\User;
use App\Models\VeterancyAward;
use App\Models\VeterancySetting;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class VeterancyService
{
    public const BRONZE = 'bronze';
    public const SILVER = 'silver';
    public const GOLD = 'gold';

    public const LEVELS = [
        self::BRONZE => ['label' => 'Veterano Bronce', 'years' => 1, 'rank' => 1, 'setting' => 'bronze_metopa_id'],
        self::SILVER => ['label' => 'Veterano Plata', 'years' => 3, 'rank' => 2, 'setting' => 'silver_metopa_id'],
        self::GOLD => ['label' => 'Veterano Oro', 'years' => 5, 'rank' => 3, 'setting' => 'gold_metopa_id'],
    ];

    private array $summaryCache = [];
    private array $processedRankCache = [];

    public function summary(User $user, ?CarbonInterface $asOf = null): array
    {
        $asOf = CarbonImmutable::instance($asOf ?? now())->startOfDay();
        $cacheKey = $user->id . '|' . $asOf->toDateString();

        if (isset($this->summaryCache[$cacheKey])) {
            return $this->summaryCache[$cacheKey];
        }

        if (! $user->member_at) {
            return $this->summaryCache[$cacheKey] = [
                'effective_days' => 0,
                'reserve_days' => 0,
                'other_inactive_days' => 0,
                'eligible_level' => null,
                'eligible_earned_at' => null,
                'pending_level' => null,
                'pending_earned_at' => null,
                'next_level' => null,
                'days_remaining' => null,
                'target_date' => null,
            ];
        }

        $start = CarbonImmutable::parse($user->member_at)->startOfDay();
        if ($start->greaterThan($asOf)) {
            return $this->summaryCache[$cacheKey] = [
                'effective_days' => 0,
                'reserve_days' => 0,
                'other_inactive_days' => 0,
                'eligible_level' => null,
                'eligible_earned_at' => null,
                'pending_level' => null,
                'pending_earned_at' => null,
                'next_level' => self::BRONZE,
                'days_remaining' => $this->thresholdDays($start, self::BRONZE),
                'target_date' => $start->addYears(1),
            ];
        }

        $state = 'ACTIVO';
        $cursor = $start;
        $effectiveDays = 0;
        $reserveDays = 0;
        $otherInactiveDays = 0;

        if (Schema::hasTable('user_status_histories')) {
            $events = DB::table('user_status_histories as h')
                ->join('status as s', 's.id', '=', 'h.to_status_id')
                ->where('h.user_id', $user->id)
                ->where('h.changed_at', '>=', $start->toDateTimeString())
                ->where('h.changed_at', '<=', $asOf->endOfDay()->toDateTimeString())
                ->orderBy('h.changed_at')
                ->orderBy('h.id')
                ->get(['h.changed_at', 's.name']);

            foreach ($events as $event) {
                $eventDate = CarbonImmutable::parse($event->changed_at)->startOfDay();
                if ($eventDate->lessThan($cursor)) {
                    continue;
                }

                $days = $cursor->diffInDays($eventDate);
                [$effectiveDays, $reserveDays, $otherInactiveDays] = $this->addDays(
                    $state,
                    $days,
                    $effectiveDays,
                    $reserveDays,
                    $otherInactiveDays,
                );

                $state = strtoupper(trim((string) $event->name));
                $cursor = $eventDate;
            }
        } else {
            $state = strtoupper(trim((string) ($user->status?->name ?? 'ACTIVO')));
        }

        $days = $cursor->diffInDays($asOf);
        [$effectiveDays, $reserveDays, $otherInactiveDays] = $this->addDays(
            $state,
            $days,
            $effectiveDays,
            $reserveDays,
            $otherInactiveDays,
        );

        $eligibleLevel = null;
        foreach (self::LEVELS as $level => $definition) {
            if ($effectiveDays >= $this->thresholdDays($start, $level)) {
                $eligibleLevel = $level;
            }
        }

        $processedRank = $this->processedRank($user);
        $pendingLevel = null;
        if ($eligibleLevel && self::LEVELS[$eligibleLevel]['rank'] > $processedRank) {
            $pendingLevel = $eligibleLevel;
        }

        $nextLevel = null;
        $daysRemaining = null;
        $targetDate = null;

        if ($pendingLevel === null) {
            foreach (self::LEVELS as $level => $definition) {
                if ($definition['rank'] <= $processedRank) {
                    continue;
                }

                $threshold = $this->thresholdDays($start, $level);
                if ($effectiveDays < $threshold) {
                    $nextLevel = $level;
                    $daysRemaining = $threshold - $effectiveDays;
                    if ($this->statusName($user) === 'ACTIVO') {
                        $targetDate = $asOf->addDays($daysRemaining);
                    }
                    break;
                }
            }
        }

        return $this->summaryCache[$cacheKey] = [
            'effective_days' => $effectiveDays,
            'reserve_days' => $reserveDays,
            'other_inactive_days' => $otherInactiveDays,
            'eligible_level' => $eligibleLevel,
            'eligible_earned_at' => $eligibleLevel ? $this->reachedAt($user, $eligibleLevel, $asOf) : null,
            'pending_level' => $pendingLevel,
            'pending_earned_at' => $pendingLevel ? $this->reachedAt($user, $pendingLevel, $asOf) : null,
            'next_level' => $nextLevel,
            'days_remaining' => $daysRemaining,
            'target_date' => $targetDate,
        ];
    }

    public function pendingUsers(): Collection
    {
        if (! Schema::hasTable('veterancy_settings')) {
            return collect();
        }

        return User::query()
            ->whereNotNull('member_at')
            ->with(['status', 'metopas'])
            ->orderBy('nick')
            ->get()
            ->filter(fn (User $user): bool => $this->summary($user)['pending_level'] !== null)
            ->values();
    }

    public function upcomingUsers(int $withinDays = 60): Collection
    {
        if (! Schema::hasTable('veterancy_settings')) {
            return collect();
        }

        return User::query()
            ->whereNotNull('member_at')
            ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
            ->with(['status', 'metopas'])
            ->orderBy('nick')
            ->get()
            ->filter(function (User $user) use ($withinDays): bool {
                $summary = $this->summary($user);

                return $summary['pending_level'] === null
                    && $summary['next_level'] !== null
                    && $summary['days_remaining'] !== null
                    && $summary['days_remaining'] >= 0
                    && $summary['days_remaining'] <= $withinDays;
            })
            ->sortBy(fn (User $user): int => (int) $this->summary($user)['days_remaining'])
            ->values();
    }

    public function idsForBand(string $level): array
    {
        if (! isset(self::LEVELS[$level])) {
            return [];
        }

        $levels = array_keys(self::LEVELS);
        $position = array_search($level, $levels, true);
        $nextLevel = $levels[$position + 1] ?? null;

        return User::query()
            ->whereNotNull('member_at')
            ->whereHas('status', fn ($query) => $query->where('name', 'ACTIVO'))
            ->with(['status', 'metopas'])
            ->get()
            ->filter(function (User $user) use ($level, $nextLevel): bool {
                $start = CarbonImmutable::parse($user->member_at)->startOfDay();
                $days = $this->summary($user)['effective_days'];
                $minimum = $this->thresholdDays($start, $level);
                $maximum = $nextLevel ? $this->thresholdDays($start, $nextLevel) : null;

                return $days >= $minimum && ($maximum === null || $days < $maximum);
            })
            ->pluck('id')
            ->map(fn ($id): int => (int) $id)
            ->all();
    }

    public function processedRank(User $user): int
    {
        if (isset($this->processedRankCache[$user->id])) {
            return $this->processedRankCache[$user->id];
        }

        $rank = 0;

        if (Schema::hasTable('veterancy_awards')) {
            $awardLevels = VeterancyAward::query()
                ->where('user_id', $user->id)
                ->pluck('level');

            foreach ($awardLevels as $level) {
                $rank = max($rank, self::LEVELS[$level]['rank'] ?? 0);
            }
        }

        if (Schema::hasTable('veterancy_settings') && Schema::hasTable('metopa_user')) {
            $setting = VeterancySetting::current();
            $activeMetopaIds = DB::table('metopa_user')
                ->where('user_id', $user->id)
                ->whereNull('deleted_at')
                ->pluck('metopa_id')
                ->map(fn ($id): int => (int) $id)
                ->all();

            foreach (self::LEVELS as $level => $definition) {
                $metopaId = (int) ($setting->{$definition['setting']} ?? 0);
                if ($metopaId > 0 && in_array($metopaId, $activeMetopaIds, true)) {
                    $rank = max($rank, $definition['rank']);
                }
            }
        }

        return $this->processedRankCache[$user->id] = $rank;
    }

    public function levelLabel(?string $level): string
    {
        return self::LEVELS[$level]['label'] ?? '—';
    }

    public function metopaIdForLevel(VeterancySetting $setting, string $level): ?int
    {
        $field = self::LEVELS[$level]['setting'] ?? null;
        if (! $field || ! $setting->{$field}) {
            return null;
        }

        return (int) $setting->{$field};
    }

    public function thresholdDays(CarbonInterface $memberAt, string $level): int
    {
        $years = self::LEVELS[$level]['years'] ?? 0;
        $start = CarbonImmutable::instance($memberAt)->startOfDay();

        return $start->diffInDays($start->addYears($years));
    }

    public function reachedAt(User $user, string $level, ?CarbonInterface $asOf = null): ?CarbonImmutable
    {
        if (! isset(self::LEVELS[$level]) || ! $user->member_at) {
            return null;
        }

        $asOf = CarbonImmutable::instance($asOf ?? now())->startOfDay();
        $start = CarbonImmutable::parse($user->member_at)->startOfDay();

        if ($start->greaterThan($asOf)) {
            return null;
        }

        $threshold = $this->thresholdDays($start, $level);
        $state = 'ACTIVO';
        $cursor = $start;
        $effectiveDays = 0;

        if (Schema::hasTable('user_status_histories')) {
            $events = DB::table('user_status_histories as h')
                ->join('status as s', 's.id', '=', 'h.to_status_id')
                ->where('h.user_id', $user->id)
                ->where('h.changed_at', '>=', $start->toDateTimeString())
                ->where('h.changed_at', '<=', $asOf->endOfDay()->toDateTimeString())
                ->orderBy('h.changed_at')
                ->orderBy('h.id')
                ->get(['h.changed_at', 's.name']);

            foreach ($events as $event) {
                $eventDate = CarbonImmutable::parse($event->changed_at)->startOfDay();
                if ($eventDate->lessThan($cursor)) {
                    continue;
                }

                if ($state === 'ACTIVO') {
                    $segmentDays = $cursor->diffInDays($eventDate);
                    if ($effectiveDays + $segmentDays >= $threshold) {
                        return $cursor->addDays($threshold - $effectiveDays);
                    }
                    $effectiveDays += $segmentDays;
                }

                $state = strtoupper(trim((string) $event->name));
                $cursor = $eventDate;
            }
        }

        if ($state === 'ACTIVO') {
            $segmentDays = $cursor->diffInDays($asOf);
            if ($effectiveDays + $segmentDays >= $threshold) {
                return $cursor->addDays($threshold - $effectiveDays);
            }
        }

        return null;
    }

    public function veteranMetopaIds(VeterancySetting $setting): array
    {
        return collect(self::LEVELS)
            ->map(fn (array $definition): int => (int) ($setting->{$definition['setting']} ?? 0))
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values()
            ->all();
    }

    public function clearRuntimeCache(): void
    {
        $this->summaryCache = [];
        $this->processedRankCache = [];
    }

    private function addDays(
        string $state,
        int $days,
        int $effective,
        int $reserve,
        int $other,
    ): array {
        if ($state === 'ACTIVO') {
            $effective += $days;
        } elseif ($state === 'RESERVA') {
            $reserve += $days;
        } else {
            $other += $days;
        }

        return [$effective, $reserve, $other];
    }

    private function statusName(User $user): string
    {
        if ($user->relationLoaded('status')) {
            return strtoupper(trim((string) ($user->status?->name ?? '')));
        }

        $name = Status::withTrashed()->whereKey($user->status_id)->value('name');

        return strtoupper(trim((string) $name));
    }
}
