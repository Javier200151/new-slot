<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\Metopa;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Models\VeterancyAward;
use App\Models\VeterancySetting;
use App\Services\MemberProcedures\TelegramNotificationService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

class VeterancyAwardService
{
    public function __construct(
        private readonly VeterancyService $veterancies,
        private readonly UserMetopaAssignmentService $metopaAssignments,
        private readonly TelegramNotificationService $telegramNotifications,
    ) {
    }

    public function approve(iterable $users, ?int $actorId = null): array
    {
        $actorId ??= Auth::id();
        if (! $actorId) {
            throw new LogicException('La aprobación necesita un usuario administrador identificado.');
        }

        $userIds = collect($users)
            ->map(fn ($user): int => $user instanceof User ? (int) $user->id : (int) $user)
            ->filter()
            ->unique()
            ->values();

        if ($userIds->isEmpty()) {
            return ['awarded' => 0, 'post_id' => null];
        }

        $result = DB::transaction(function () use ($userIds, $actorId): array {
            $setting = VeterancySetting::current()->load([
                'forumCategory',
                'bronzeMetopa',
                'silverMetopa',
                'goldMetopa',
            ]);

            if (! $setting->forumCategory) {
                throw new LogicException('Selecciona la subcategoría del foro en la configuración de veteranías.');
            }

            $lockedUsers = User::query()
                ->whereKey($userIds)
                ->lockForUpdate()
                ->with(['status', 'metopas'])
                ->get();

            $this->veterancies->clearRuntimeCache();
            $plans = collect();

            foreach ($lockedUsers as $user) {
                $summary = $this->veterancies->summary($user);
                $level = $summary['pending_level'];
                if (! $level) {
                    continue;
                }

                $metopaId = $this->veterancies->metopaIdForLevel($setting, $level);
                if (! $metopaId || ! Metopa::withTrashed()->whereKey($metopaId)->exists()) {
                    throw new LogicException('Configura la metopa correspondiente a ' . $this->veterancies->levelLabel($level) . '.');
                }

                if (VeterancyAward::query()->where('user_id', $user->id)->where('level', $level)->exists()) {
                    continue;
                }

                $earnedAt = $summary['pending_earned_at'] ?? $this->veterancies->reachedAt($user, $level);

                $plans->push([
                    'user' => $user,
                    'level' => $level,
                    'metopa_id' => $metopaId,
                    'effective_days' => (int) $summary['effective_days'],
                    'earned_at' => $earnedAt,
                ]);
            }

            if ($plans->isEmpty()) {
                return ['awarded' => 0, 'post_id' => null];
            }

            $post = CommunityPost::create([
                'channel' => $setting->forumCategory->channel ?: 'personal',
                'forum_category_id' => $setting->forumCategory->id,
                'user_id' => $actorId,
                'title' => $this->buildPostTitle($plans),
                'body' => $this->buildPostBody($setting, $plans),
            ]);

            // Igual que un hilo creado desde el foro, el autor queda suscrito
            // a las respuestas posteriores de la publicación automática.
            $post->subscriptions()->firstOrCreate([
                'user_id' => $actorId,
            ]);

            foreach ($plans as $plan) {
                /** @var User $user */
                $user = $plan['user'];

                $this->removePreviousVeterancyMetopas($setting, $user, (int) $plan['metopa_id']);

                $this->metopaAssignments->assign(
                    userId: (int) $user->id,
                    metopaId: (int) $plan['metopa_id'],
                    assignedAt: $plan['earned_at'] ?? now(),
                    updateExisting: true,
                    preserveAssignedAtOnRestore: true,
                );

                VeterancyAward::create([
                    'user_id' => $user->id,
                    'level' => $plan['level'],
                    'metopa_id' => $plan['metopa_id'],
                    'effective_days' => $plan['effective_days'],
                    'earned_at' => $plan['earned_at'] ?? now(),
                    'approved_at' => now(),
                    'approved_by_user_id' => $actorId,
                    'community_post_id' => $post->id,
                ]);
            }

            $this->veterancies->clearRuntimeCache();

            return [
                'awarded' => $plans->count(),
                'post_id' => $post->id,
                'telegram_awards' => $plans->map(fn (array $plan): array => [
                    'nick' => (string) $plan['user']->nick,
                    'level' => (string) $plan['level'],
                ])->values()->all(),
            ];
        });

        if (($result['awarded'] ?? 0) > 0 && ! empty($result['post_id']) && $this->telegramNotifications->isConfigured()) {
            try {
                $post = CommunityPost::query()->with('forumCategory')->findOrFail((int) $result['post_id']);
                $setting = MemberProcedureSetting::current();
                $result['telegram'] = $this->telegramNotifications->sendVeterancyUpdate(
                    $post,
                    (array) ($result['telegram_awards'] ?? []),
                    $setting,
                );
            } catch (Throwable $exception) {
                report($exception);
                $result['telegram_error'] = $exception->getMessage();
            }
        }

        unset($result['telegram_awards']);

        return $result;
    }

    private function buildPostTitle(Collection $plans): string
    {
        $nicks = $plans
            ->map(fn (array $plan): string => trim((string) $plan['user']->nick))
            ->filter()
            ->unique()
            ->values();

        $title = 'Veteranias ' . $nicks->implode(', ');

        if (mb_strlen($title) > 180) {
            throw new LogicException('El título automático de veteranías supera el límite del foro. Aprueba menos usuarios en este lote.');
        }

        return $title;
    }

    private function buildPostBody(VeterancySetting $setting, Collection $plans): string
    {
        $parts = [];
        $intro = trim((string) $setting->post_body);
        if ($intro !== '') {
            $parts[] = $intro;
        }

        foreach (array_keys(VeterancyService::LEVELS) as $level) {
            $group = $plans
                ->filter(fn (array $plan): bool => $plan['level'] === $level)
                ->sortBy(fn (array $plan): string => strtolower((string) $plan['user']->nick))
                ->values();

            if ($group->isEmpty()) {
                continue;
            }

            $section = ['[h3]' . $this->veterancies->levelLabel($level) . '[/h3]'];

            $metopa = Metopa::withTrashed()->find((int) $group->first()['metopa_id']);
            if (filled($metopa?->image)) {
                $section[] = '[img]' . url('storage/' . ltrim((string) $metopa->image, '/')) . '[/img]';
            }

            $section[] = '[list]';
            foreach ($group as $plan) {
                $section[] = '[*][b]' . $plan['user']->nick . '[/b]';
            }
            $section[] = '[/list]';

            $parts[] = implode("\n", $section);
        }

        return implode("\n\n", $parts);
    }

    private function removePreviousVeterancyMetopas(VeterancySetting $setting, User $user, int $currentMetopaId): void
    {
        foreach ($this->veterancies->veteranMetopaIds($setting) as $metopaId) {
            if ($metopaId === $currentMetopaId) {
                continue;
            }

            if ($user->metopas->contains('id', $metopaId)) {
                $this->metopaAssignments->delete((int) $user->id, $metopaId);
            }
        }
    }
}
