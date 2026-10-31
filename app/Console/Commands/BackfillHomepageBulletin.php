<?php

namespace App\Console\Commands;

use App\Models\CommunityPost;
use App\Models\HomepageNews;
use App\Models\MemberProcedure;
use App\Models\SqaGroup;
use App\Models\SqaGroupUser;
use App\Models\Status;
use App\Models\User;
use App\Support\CommunityForumCategory;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Recupera anuncios desde hechos ya registrados, sin ejecutar acciones de negocio. */
class BackfillHomepageBulletin extends Command
{
    protected $signature = 'sqa:backfill-bulletin {--preview : Mostrar lo que se recuperaría sin guardar anuncios}';

    protected $description = 'Recupera anuncios históricos de procedimientos y movimientos de grupos SQA';

    private array $users = [];
    private array $groups = [];
    private array $statusNames = [];
    private array $statusIds = [];
    private array $claimedHistories = [];
    private array $found = [];
    private array $counts = ['procedimientos' => 0, 'estados' => 0, 'incorporaciones' => 0, 'salidas' => 0, 'coordinadores' => 0];
    private int $already = 0;
    private int $missing = 0;
    private bool $preview = false;

    public function handle(): int
    {
        foreach (['homepage_news', 'member_procedures', 'user_status_histories', 'sqa_group_users', 'users', 'sqa_groups', 'status'] as $table) {
            if (! Schema::hasTable($table)) {
                $this->error("Falta la tabla {$table}. Aplica antes las migraciones del tablón.");
                return self::FAILURE;
            }
        }
        if (! Schema::hasColumn('homepage_news', 'source_key')) {
            $this->error('Falta la migración del tablón de anuncios.');
            return self::FAILURE;
        }

        $this->preview = (bool) $this->option('preview');
        $this->users = User::withTrashed()->get(['id', 'nick', 'promo_id', 'deleted_at'])->keyBy('id')->all();
        $this->groups = SqaGroup::withTrashed()->get(['id', 'name', 'large_name'])->keyBy('id')->all();
        $this->statusNames = Status::withTrashed()->pluck('name', 'id')->map(fn ($name) => mb_strtoupper(trim($name)))->all();
        $this->statusIds = array_flip($this->statusNames);
        $this->info($this->preview ? 'Vista previa (sin modificar datos).' : 'Recuperando anuncios históricos…');

        $this->backfillProcedures();
        $this->backfillStatusChanges();
        $this->backfillGroupMemberships();
        $this->backfillGroupAudit();

        $this->table(['Origen', $this->preview ? 'Pendientes' : 'Creados'], collect($this->counts)->map(fn ($n, $key) => [$key, $n])->values()->all());
        $this->line('Existentes (no modificados): '.$this->already.'. Registros sin datos suficientes: '.$this->missing.'.');
        $this->info($this->preview ? 'Para importar, repite el comando sin --preview.' : 'Importación terminada. Puedes repetir este comando sin duplicar los anuncios.');

        return self::SUCCESS;
    }

    private function backfillProcedures(): void
    {
        MemberProcedure::query()
            ->where('status', MemberProcedure::STATUS_COMPLETED)
            ->orderBy('id')
            ->chunkById(200, function ($procedures): void {
                foreach ($procedures as $procedure) {
                    $user = $this->users[(int) $procedure->user_id] ?? null;
                    $date = $procedure->completed_at ?? $procedure->started_at;
                    $details = $user ? $this->procedureDescription($procedure->type, $user) : null;
                    if (! $details || ! $date) {
                        $this->missing++;
                        continue;
                    }
                    $history = $this->historyForProcedure($procedure);
                    // Mismo identificador que los avisos que el sistema ya emitió en tiempo real.
                    $key = $history ? 'status:'.$history->id : 'historic:procedure:'.$procedure->id;
                    if ($history) {
                        $this->claimedHistories[(int) $history->id] = true;
                        $date = $history->changed_at;
                    }
                    $postId = (int) (($procedure->input ?? [])['forum_post_id'] ?? 0);
                    $this->record($key, $details[0], $details[1], $details[2], $this->forumUrl($postId), $date, 'procedimientos');
                }
            });
    }

    /** Recupera cambios automáticos sin procedimiento asociado (nunca líneas baseline/documentadas). */
    private function backfillStatusChanges(): void
    {
        DB::table('user_status_histories')->where('source', 'automatic')->orderBy('id')
            ->chunkById(200, function ($histories): void {
                foreach ($histories as $history) {
                    if (isset($this->claimedHistories[(int) $history->id])) {
                        continue;
                    }
                    $user = $this->users[(int) $history->user_id] ?? null;
                    if (! $user || ! $history->changed_at) {
                        $this->missing++;
                        continue;
                    }
                    $from = $this->statusNames[(int) $history->from_status_id] ?? '';
                    $to = $this->statusNames[(int) $history->to_status_id] ?? '';
                    $details = $this->statusDescription($from, $to, $user);
                    if (! $details) {
                        continue;
                    }
                    $this->record('status:'.$history->id, $details[0], $details[1], $details[2], null, $history->changed_at, 'estados');
                }
            });
    }

    private function backfillGroupMemberships(): void
    {
        SqaGroupUser::withTrashed()->orderBy('id')->chunkById(200, function ($memberships): void {
            foreach ($memberships as $membership) {
                if (! ($this->users[(int) $membership->user_id] ?? null)
                    || ! ($this->groups[(int) $membership->sqa_group_id] ?? null)) {
                    $this->missing++;
                    continue;
                }
                $id = (int) $membership->id;
                if ($membership->created_at) {
                    $this->record('group:'.$id, 'grupo', 'Incorporación a '.$this->groupName($membership->sqa_group_id),
                        $this->users[(int) $membership->user_id]->nick.' se incorpora al grupo de trabajo '.$this->groupName($membership->sqa_group_id).'.',
                        $this->userUrl($membership->user_id), $membership->created_at, 'incorporaciones');
                }
                if ($membership->deleted_at && ! $this->publishedNearby('group-left:'.$id.':%', CarbonImmutable::instance($membership->deleted_at))) {
                    $this->record('group-left:'.$id.':'.$membership->deleted_at->format('YmdHisu'), 'grupo',
                        'Salida de '.$this->groupName($membership->sqa_group_id),
                        $this->users[(int) $membership->user_id]->nick.' deja el grupo de trabajo '.$this->groupName($membership->sqa_group_id).'.',
                        $this->userUrl($membership->user_id), $membership->deleted_at, 'salidas');
                }
            }
        });
    }

    /** Utiliza auditoría para recuperar salidas/restauraciones previas y cambios de coordinador. */
    private function backfillGroupAudit(): void
    {
        if (! Schema::hasTable('activity_log')) {
            return;
        }
        DB::table('activity_log')->where('subject_type', SqaGroupUser::class)
            ->whereIn('event', ['created', 'deleted', 'restored', 'updated'])->orderBy('id')
            ->chunkById(200, function ($entries): void {
                foreach ($entries as $entry) {
                    if (! $entry->created_at) {
                        continue;
                    }
                    $changes = json_decode($entry->properties ?: $entry->attribute_changes ?: '{}', true);
                    $new = is_array($changes['attributes'] ?? null) ? $changes['attributes'] : [];
                    $old = is_array($changes['old'] ?? null) ? $changes['old'] : [];
                    $membership = SqaGroupUser::withTrashed()->find($entry->subject_id);
                    $userId = (int) ($membership?->user_id ?? $new['user_id'] ?? $old['user_id'] ?? 0);
                    $groupId = (int) ($membership?->sqa_group_id ?? $new['sqa_group_id'] ?? $old['sqa_group_id'] ?? 0);
                    $user = $this->users[$userId] ?? null;
                    $group = $this->groups[$groupId] ?? null;
                    if (! $user || ! $group) {
                        $this->missing++;
                        continue;
                    }
                    $time = CarbonImmutable::parse($entry->created_at);
                    $id = (int) $entry->subject_id;
                    if ($entry->event === 'deleted') {
                        // La última baja ya se recuperó desde deleted_at de la pertenencia.
                        if ($membership?->deleted_at && abs($membership->deleted_at->getTimestamp() - $time->getTimestamp()) <= 120) {
                            continue;
                        }
                        if ($this->publishedNearby('group-left:'.$id.':%', $time)) {
                            continue;
                        }
                        $this->record('historic:group-left-log:'.$entry->id, 'grupo', 'Salida de '.$this->groupName($groupId),
                            $user->nick.' deja el grupo de trabajo '.$this->groupName($groupId).'.',
                            $this->userUrl($userId), $time, 'salidas');
                    } elseif ($entry->event === 'restored') {
                        if ($this->publishedNearby('group-return:'.$id.':%', $time)) {
                            continue;
                        }
                        $this->record('historic:group-return-log:'.$entry->id, 'grupo', 'Reincorporación a '.$this->groupName($groupId),
                            $user->nick.' se reincorpora al grupo de trabajo '.$this->groupName($groupId).'.',
                            $this->userUrl($userId), $time, 'incorporaciones');
                    } elseif (in_array($entry->event, ['created', 'updated'], true) && array_key_exists('coordinator', $new)
                        && filter_var($new['coordinator'], FILTER_VALIDATE_BOOLEAN)) {
                        if ($this->publishedNearby('group-coordinator:'.$id.':%', $time)) {
                            continue;
                        }
                        $this->record('historic:group-coordinator-log:'.$entry->id, 'grupo',
                            'Nuevo coordinador de '.$this->groupName($groupId),
                            $user->nick.' ha sido designado coordinador de '.$this->groupName($groupId).'.',
                            $this->userUrl($userId), $time, 'coordinadores');
                    }
                }
            });
    }

    private function historyForProcedure(MemberProcedure $procedure): ?object
    {
        $to = match ($procedure->type) {
            MemberProcedure::TYPE_RECRUITMENT_START => 'RECLUTA',
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE, MemberProcedure::TYPE_REACTIVATION => 'ACTIVO',
            MemberProcedure::TYPE_RESERVE => 'RESERVA',
            MemberProcedure::TYPE_DEPARTURE => 'BAJA',
            MemberProcedure::TYPE_DISMISSAL => 'CESADO',
            MemberProcedure::TYPE_NOT_PROMOTED => 'NO PROMOCIONADO',
            default => null,
        };
        if (! $to || ! isset($this->statusIds[$to])) {
            return null;
        }
        $query = DB::table('user_status_histories')->where('source', 'automatic')
            ->where('user_id', $procedure->user_id)->where('to_status_id', $this->statusIds[$to])
            ->whereBetween('changed_at', [($procedure->started_at ?? $procedure->created_at)->copy()->subMinutes(2),
                ($procedure->completed_at ?? $procedure->updated_at)->copy()->addMinutes(5)]);
        $from = match ($procedure->type) {
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => 'RECLUTA',
            MemberProcedure::TYPE_REACTIVATION => 'RESERVA',
            MemberProcedure::TYPE_RESERVE => 'ACTIVO',
            default => null,
        };
        if ($from && isset($this->statusIds[$from])) {
            $query->where('from_status_id', $this->statusIds[$from]);
        }
        foreach ($query->orderBy('changed_at')->get() as $history) {
            if (! isset($this->claimedHistories[(int) $history->id])) {
                return $history;
            }
        }
        return null;
    }

    private function procedureDescription(string $type, User $user): ?array
    {
        $nick = (string) $user->nick;
        return match ($type) {
            MemberProcedure::TYPE_RECRUITMENT_START => ['reclutamiento', 'Nuevo recluta: '.$nick, $nick.' comienza su periodo de reclutamiento en Squad ALPHA.'],
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => ['promocion', 'Nuevo miembro: '.$nick,
                $nick.' ha sido promocionado a miembro activo'.((int) $user->promo_id > 1 ? ' · Calavera #'.$user->promo_id : '').'.'],
            MemberProcedure::TYPE_NOT_PROMOTED => ['reclutamiento', 'Reclutamiento finalizado: '.$nick,
                $nick.' ha finalizado el reclutamiento sin promocionar a miembro.'],
            MemberProcedure::TYPE_REACTIVATION => ['comunidad', 'Reincorporación: '.$nick, $nick.' vuelve al servicio activo de Squad ALPHA.'],
            MemberProcedure::TYPE_RESERVE => ['comunidad', 'Paso a reserva: '.$nick, $nick.' pasa a la reserva de Squad ALPHA.'],
            MemberProcedure::TYPE_DEPARTURE => ['baja', 'Baja de miembro: '.$nick, $nick.' deja de pertenecer a los miembros activos de Squad ALPHA.'],
            MemberProcedure::TYPE_DISMISSAL => ['baja', 'Cese de miembro: '.$nick, $nick.' causa baja de Squad ALPHA.'],
            default => null,
        };
    }

    private function statusDescription(string $from, string $to, User $user): ?array
    {
        $nick = (string) $user->nick;
        if ($to === 'RECLUTA') return $this->procedureDescription(MemberProcedure::TYPE_RECRUITMENT_START, $user);
        if ($to === 'ACTIVO' && $from === 'RECLUTA') return $this->procedureDescription(MemberProcedure::TYPE_RECRUITMENT_COMPLETE, $user);
        if ($to === 'ACTIVO' && $from === 'RESERVA') return $this->procedureDescription(MemberProcedure::TYPE_REACTIVATION, $user);
        if ($to === 'RESERVA' && $from === 'ACTIVO') return $this->procedureDescription(MemberProcedure::TYPE_RESERVE, $user);
        if ($to === 'BAJA') return $this->procedureDescription(MemberProcedure::TYPE_DEPARTURE, $user);
        if ($to === 'CESADO') return $this->procedureDescription(MemberProcedure::TYPE_DISMISSAL, $user);
        if ($to === 'NO PROMOCIONADO') return $this->procedureDescription(MemberProcedure::TYPE_NOT_PROMOTED, $user);
        if ($to === 'ACTIVO' && $from !== 'ACTIVO') {
            return ['comunidad', 'Alta de miembro: '.$nick, $nick.' se incorpora como miembro activo de Squad ALPHA.'];
        }
        return null;
    }

    private function forumUrl(int $postId): ?string
    {
        if (! $postId) return null;
        $post = CommunityPost::query()->with('forumCategory')->find($postId);
        if (! $post || $post->channel !== 'personal' || ! $post->forumCategory?->is_enabled) return null;
        return route('community.forum.show', [CommunityForumCategory::keyForPost($post), $post]);
    }

    private function groupName(int $groupId): string
    {
        $group = $this->groups[$groupId];
        return (string) ($group->large_name ?: $group->name);
    }

    private function userUrl(int $userId): ?string
    {
        $user = $this->users[$userId] ?? null;
        return $user && ! $user->trashed() ? route('users.show', ['user' => $user->nick]) : null;
    }

    private function publishedNearby(string $sourcePattern, CarbonImmutable $date): bool
    {
        return HomepageNews::withTrashed()->where('source_type', 'automatic')
            ->where('source_key', 'like', $sourcePattern)
            ->whereBetween('published_at', [$date->subMinutes(2), $date->addMinutes(2)])->exists();
    }

    private function record(string $key, string $category, string $title, string $description, ?string $url, $date, string $origin): void
    {
        if (isset($this->found[$key])) return;
        $this->found[$key] = true;
        if (HomepageNews::withTrashed()->where('source_key', $key)->exists()) {
            $this->already++;
            return;
        }
        if ($this->preview) {
            $this->counts[$origin]++;
            return;
        }
        // INSERT IGNORE sobre la clave única source_key evita duplicados ante ejecuciones concurrentes.
        $created = DB::table('homepage_news')->insertOrIgnore([
            'source_key' => $key,
            'source_type' => 'automatic',
            'category' => $category,
            'title' => mb_substr($title, 0, 180),
            'excerpt' => $description,
            'external_url' => $url,
            'is_published' => true,
            'published_at' => CarbonImmutable::parse($date)->toDateTimeString(),
            'sort_order' => 100,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        if ($created) $this->counts[$origin]++;
        else $this->already++;
    }
}
