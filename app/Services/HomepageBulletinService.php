<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\VeterancyAward;
use App\Models\HomepageNews;
use App\Models\MemberProcedure;
use App\Support\CommunityForumCategory;
use App\Models\SqaGroupUser;
use App\Models\Status;
use App\Models\User;
use App\Models\UserStatusHistory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Publica exclusivamente acontecimientos nuevos; nunca reconstruye cambios históricos. */
class HomepageBulletinService
{
    public function initialRecruit(User $user): void
    {
        $userId = (int) $user->getKey();
        $this->whenCommitted(function () use ($userId): void {
            $user = User::withTrashed()->find($userId);
            if (! $user || $this->statusName($user->status_id) !== 'RECLUTA') {
                return;
            }
            $this->publish(
                'recruit:'.$userId,
                'reclutamiento',
                'Nuevo recluta: '.$user->nick,
                $user->nick.' inicia su periodo de reclutamiento en Squad ALPHA.',
                $this->memberUrl($user),
            );
        });
    }

    public function statusChanged(UserStatusHistory $history): void
    {
        if ($history->source !== 'automatic') {
            return;
        }
        $historyId = (int) $history->getKey();
        $this->whenCommitted(function () use ($historyId): void {
            $history = UserStatusHistory::find($historyId);
            $user = $history?->user;
            if (! $history || ! $user) {
                return;
            }
            $previous = $this->statusName($history->from_status_id);
            $current = $this->statusName($history->to_status_id);
            $nick = (string) $user->nick;
            $category = null;
            $title = '';
            $description = '';
            $url = null;

            if ($current === 'RECLUTA') {
                $category = 'reclutamiento';
                $title = 'Nuevo recluta: '.$nick;
                $description = $nick.' comienza su periodo de reclutamiento en Squad ALPHA.';
            } elseif ($current === 'ACTIVO' && $previous === 'RECLUTA') {
                $category = 'promocion';
                $title = 'Nuevo miembro: '.$nick;
                $number = (int) $user->promo_id;
                $description = $nick.' ha sido promocionado a miembro activo'.($number > 1 ? ' · Calavera #'.$number : '').'.';
            } elseif ($current === 'ACTIVO' && $previous === 'RESERVA') {
                $category = 'comunidad';
                $title = 'Reincorporación: '.$nick;
                $description = $nick.' vuelve al servicio activo de Squad ALPHA.';
            } elseif ($current === 'ACTIVO' && ! in_array($previous, ['ACTIVO', 'RECLUTA', 'RESERVA'], true)) {
                $category = 'comunidad';
                $title = 'Alta de miembro: '.$nick;
                $description = $nick.' se incorpora como miembro activo de Squad ALPHA.';
            } elseif (in_array($current, ['BAJA', 'CESADO'], true)) {
                $category = 'baja';
                $title = 'Baja de miembro: '.$nick;
                $description = $nick.' deja de pertenecer a los miembros activos de Squad ALPHA.';
                $url = null;
            } elseif ($current === 'RESERVA' && $previous === 'ACTIVO') {
                $category = 'comunidad';
                $title = 'Paso a reserva: '.$nick;
                $description = $nick.' pasa a la reserva de Squad ALPHA.';
            }

            if ($category !== null) {
                $url = $this->forumUrlForProcedure($history, $previous, $current) ?? $url;
                $this->publish('status:'.$historyId, $category, $title, $description, $url);
            }
        });
    }

    public function groupJoined(SqaGroupUser $membership, bool $restored = false): void
    {
        $membershipId = (int) $membership->getKey();
        // Restaurar equivale a una nueva incorporación, sin repetir el aviso antiguo.
        $key = $restored ? 'group-return:'.$membershipId.':'.now()->format('YmdHisu') : 'group:'.$membershipId;
        $this->whenCommitted(function () use ($membershipId, $key): void {
            $membership = SqaGroupUser::withTrashed()->with(['user', 'sqaGroup'])->find($membershipId);
            if (! $membership || $membership->trashed() || ! $membership->user || ! $membership->sqaGroup) {
                return;
            }
            if (! in_array($this->statusName($membership->user->status_id), ['ACTIVO', 'RESERVA', 'RECLUTA'], true)) {
                return;
            }
            $nick = (string) $membership->user->nick;
            $groupName = (string) ($membership->sqaGroup->large_name ?: $membership->sqaGroup->name);
            $this->publish(
                $key,
                'grupo',
                'Incorporación a '.$groupName,
                $nick.' se incorpora al grupo de trabajo '.$groupName.'.',
                $this->memberUrl($membership->user),
            );
        });
    }

    /** Un anuncio nuevo por cada salida efectiva (incluidas las siguientes a una reincorporación). */
    public function groupLeft(SqaGroupUser $membership): void
    {
        $id = (int) $membership->getKey();
        $userId = (int) $membership->user_id;
        $groupId = (int) $membership->sqa_group_id;
        $version = $membership->deleted_at?->format('YmdHisu') ?? now()->format('YmdHisu');
        $this->whenCommitted(function () use ($id, $version, $userId, $groupId): void {
            $membership = SqaGroupUser::withTrashed()->find($id);
            if ($membership && ! $membership->trashed()) {
                return; // La baja se revirtió antes del commit.
            }
            $user = User::withTrashed()->find($userId);
            $group = \App\Models\SqaGroup::withTrashed()->find($groupId);
            if (! $user || ! $group) {
                return;
            }
            $groupName = (string) ($group->large_name ?: $group->name);
            $this->publish(
                'group-left:'.$id.':'.$version,
                'grupo',
                'Salida de '.$groupName,
                $user->nick.' deja el grupo de trabajo '.$groupName.'.',
                $this->memberUrl($user),
            );
        });
    }

    /** Solo se publica el nombramiento, no las escrituras que mantienen el mismo coordinador. */
    public function groupCoordinatorAssigned(SqaGroupUser $membership): void
    {
        $id = (int) $membership->getKey();
        $key = 'group-coordinator:'.$id.':'.now()->format('YmdHisu');
        $this->whenCommitted(function () use ($id, $key): void {
            $membership = SqaGroupUser::withTrashed()->with(['user', 'sqaGroup'])->find($id);
            if (! $membership || $membership->trashed() || ! $membership->coordinator || ! $membership->user || ! $membership->sqaGroup) {
                return;
            }
            $groupName = (string) ($membership->sqaGroup->large_name ?: $membership->sqaGroup->name);
            $this->publish(
                $key,
                'grupo',
                'Nuevo coordinador de '.$groupName,
                $membership->user->nick.' ha sido designado coordinador de '.$groupName.'.',
                $this->memberUrl($membership->user),
            );
        });
    }

    /** Un anuncio para todo el lote aprobado, usando exactamente las veteranías registradas. */
    public function veteranciesAwarded(int $postId): void
    {
        $this->whenCommitted(function () use ($postId): void {
            $awards = VeterancyAward::query()->with('user')
                ->where('community_post_id', $postId)->get();
            if ($awards->isEmpty()) {
                return;
            }

            $groups = [];
            foreach (['gold', 'silver', 'bronze'] as $level) {
                $nicks = $awards->where('level', $level)
                    ->map(fn (VeterancyAward $award): string => (string) ($award->user?->nick ?? ''))
                    ->filter()->unique()->sort(SORT_NATURAL | SORT_FLAG_CASE)->values()->all();
                if ($nicks !== []) {
                    $groups[$level] = $nicks;
                }
            }
            if ($groups === []) {
                return;
            }

            $post = CommunityPost::query()->with('forumCategory')->find($postId);
            $url = $post && $post->forumCategory
                ? route('community.forum.show', [CommunityForumCategory::keyForPost($post), $post])
                : null;
            $count = collect($groups)->sum(fn (array $names): int => count($names));
            $title = 'Veteranías de Squad ALPHA';
            $excerpt = $count === 1
                ? 'Reconocemos una nueva veteranía en Squad ALPHA.'
                : 'Reconocemos '.$count.' nuevas veteranías en Squad ALPHA.';

            $this->publish('veterancy:'.$postId, 'veterania', $title, $excerpt, $url, $groups);
        });
    }

    /** Al añadir, modificar o quitar el hilo en Filament, actualizar el anuncio ya publicado. */
    public function synchronizeProcedureThread(MemberProcedure $procedure): void
    {
        $statusPair = match ($procedure->type) {
            MemberProcedure::TYPE_RECRUITMENT_START => [null, 'RECLUTA'],
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => ['RECLUTA', 'ACTIVO'],
            MemberProcedure::TYPE_REACTIVATION => ['RESERVA', 'ACTIVO'],
            MemberProcedure::TYPE_RESERVE => ['ACTIVO', 'RESERVA'],
            MemberProcedure::TYPE_DEPARTURE => [null, 'BAJA'],
            MemberProcedure::TYPE_DISMISSAL => [null, 'CESADO'],
            default => null,
        };
        if (! $statusPair || ! $procedure->started_at) {
            return;
        }

        [$previous, $current] = $statusPair;
        $history = UserStatusHistory::query()
            ->where('user_id', $procedure->user_id)
            ->where('source', 'automatic')
            ->where('changed_at', '>=', $procedure->started_at->copy()->subMinute())
            ->where('changed_at', '<=', ($procedure->completed_at ?? now())->copy()->addMinutes(5))
            ->whereHas('toStatus', fn ($query) => $query->where('name', $current))
            ->when($previous, fn ($query) => $query->whereHas('fromStatus', fn ($q) => $q->where('name', $previous)))
            ->orderBy('changed_at')
            ->first();
        if (! $history) {
            return; // Cuando cambie el estado, statusChanged utilizará el hilo guardado.
        }

        $announcement = HomepageNews::query()->where('source_key', 'status:'.$history->id)
            ->where('source_type', 'automatic')->first();
        if (! $announcement) {
            return;
        }

        $postId = (int) (($procedure->input ?? [])['forum_post_id'] ?? 0);
        $announcement->update(['external_url' => $this->forumUrlForPostId($postId)]);
    }

    private function forumUrlForPostId(int $postId): ?string
    {
        if ($postId <= 0) {
            return null;
        }
        $post = CommunityPost::query()->with('forumCategory')->find($postId);
        if (! $post || $post->channel !== 'personal' || ! $post->forumCategory?->is_enabled) {
            return null;
        }

        return route('community.forum.show', [CommunityForumCategory::keyForPost($post), $post]);
    }

    /** Aplica el hilo elegido al iniciar el procedimiento, sin alterar el foro. */
    private function forumUrlForProcedure(UserStatusHistory $history, string $previous, string $current): ?string
    {
        $type = match (true) {
            $current === 'RECLUTA' => MemberProcedure::TYPE_RECRUITMENT_START,
            $current === 'ACTIVO' && $previous === 'RECLUTA' => MemberProcedure::TYPE_RECRUITMENT_COMPLETE,
            $current === 'ACTIVO' && $previous === 'RESERVA' => MemberProcedure::TYPE_REACTIVATION,
            $current === 'RESERVA' => MemberProcedure::TYPE_RESERVE,
            $current === 'BAJA' => MemberProcedure::TYPE_DEPARTURE,
            $current === 'CESADO' => MemberProcedure::TYPE_DISMISSAL,
            default => null,
        };

        if (! $type) {
            return null;
        }

        $changedAt = $history->changed_at ?? $history->created_at ?? now();
        $procedure = MemberProcedure::query()
            ->where('user_id', $history->user_id)
            ->where('type', $type)
            ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR, MemberProcedure::STATUS_COMPLETED])
            ->where('started_at', '<=', $changedAt->copy()->addMinutes(1))
            ->where(fn ($query) => $query->whereNull('completed_at')
                ->orWhere('completed_at', '>=', $changedAt->copy()->subMinutes(5)))
            ->latest('started_at')
            ->first();

        return $this->forumUrlForPostId((int) (($procedure?->input ?? [])['forum_post_id'] ?? 0));
    }

    private function publish(string $key, string $category, string $title, string $description, ?string $url, ?array $veterancyGroups = null): void
    {
        // Permite desplegar el código antes de ejecutar la migración sin bloquear operaciones de usuarios.
        try {
            if (! Schema::hasTable('homepage_news') || ! Schema::hasColumn('homepage_news', 'source_key')) {
                return;
            }
            $attributes = [
                'category' => $category,
                'source_type' => 'automatic',
                'title' => mb_substr($title, 0, 180),
                'excerpt' => $description,
                'external_url' => $url,
                'is_published' => true,
                'published_at' => now(),
                'sort_order' => 100,
            ];
            if ($veterancyGroups !== null && Schema::hasColumn('homepage_news', 'veterancy_groups')) {
                $attributes['veterancy_groups'] = $veterancyGroups;
            }
            HomepageNews::withTrashed()->firstOrCreate(['source_key' => $key], $attributes);
        } catch (\Throwable $exception) {
            report($exception); // Un error del tablón nunca debe impedir la gestión del miembro.
        }
    }

    private function memberUrl(User $user): ?string
    {
        return $user->trashed() ? null : route('users.show', ['user' => $user->nick]);
    }

    private function statusName(?int $statusId): string
    {
        return $statusId ? mb_strtoupper(trim((string) Status::withTrashed()->whereKey($statusId)->value('name'))) : '';
    }

    private function whenCommitted(\Closure $callback): void
    {
        // Publicar solo una vez confirmada la escritura principal (y no tras un rollback).
        if (DB::transactionLevel() > 0) {
            DB::afterCommit(function () use ($callback): void {
                try { $callback(); } catch (\Throwable $exception) { report($exception); }
            });
            return;
        }
        try { $callback(); } catch (\Throwable $exception) { report($exception); }
    }
}
