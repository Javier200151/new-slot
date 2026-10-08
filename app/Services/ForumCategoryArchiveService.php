<?php

namespace App\Services;

use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\ForumCategory;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Status;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;

class ForumCategoryArchiveService
{
    public const FORMAT = 'newslot-forum-category';
    public const VERSION = 1;

    public function export(ForumCategory $category): array
    {
        if ($category->isDiary()) {
            throw new RuntimeException('Diario es una categoría interna y no utiliza copias JSON de categorías normales.');
        }

        $category->loadMissing('statuses');
        $posts = DB::table('community_posts')
            ->where('forum_category_id', $category->id)
            ->orderBy('id')
            ->get();

        return [
            'format' => self::FORMAT,
            'version' => self::VERSION,
            'exported_at' => now()->toIso8601String(),
            'category' => [
                'slug' => $category->slug,
                'title' => $category->title,
                'singular' => $category->singular,
                'description' => $category->description,
                'hint' => $category->hint,
                'icon' => $category->icon,
                'color' => $category->color,
                'process_type' => $category->process_type,
                'is_enabled' => (bool) $category->is_enabled,
                'allow_polls' => (bool) $category->allow_polls,
                'sort_order' => (int) $category->sort_order,
                'created_at' => $category->created_at?->toIso8601String(),
                'updated_at' => $category->updated_at?->toIso8601String(),
                'statuses' => $category->statuses->pluck('name')->values()->all(),
                'permissions' => $this->exportPermissions($category),
            ],
            'posts' => $posts->map(fn ($post): array => $this->exportPost($post))->values()->all(),
        ];
    }

    public function json(ForumCategory $category): string
    {
        return json_encode(
            $this->export($category),
            JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR,
        );
    }

    public function importFile(string $path): ForumCategory
    {
        $contents = @file_get_contents($path);
        if ($contents === false) {
            throw ValidationException::withMessages(['backup' => 'No se pudo leer el archivo JSON.']);
        }

        try {
            $payload = json_decode($contents, true, flags: JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw ValidationException::withMessages(['backup' => 'El archivo no contiene un JSON válido.']);
        }

        return $this->import($payload);
    }

    public function import(array $payload): ForumCategory
    {
        $this->validatePayload($payload);

        $categoryData = $payload['category'];
        $slug = Str::slug((string) $categoryData['slug']);

        if ($slug === 'diario' || in_array($slug, ForumCategory::RESERVED_SLUGS, true)) {
            throw ValidationException::withMessages(['backup' => 'El identificador de la categoría está reservado y no se puede importar.']);
        }

        if (ForumCategory::query()->where('slug', $slug)->exists()) {
            throw ValidationException::withMessages([
                'backup' => "Ya existe una categoría con el identificador '{$slug}'. Elimínala o cambia su identificador antes de importar la copia.",
            ]);
        }

        $statusNames = collect($categoryData['statuses'] ?? [])->filter()->unique()->values();
        $statuses = Status::withTrashed()->whereIn('name', $statusNames)->get()->keyBy('name');
        $missingStatuses = $statusNames->reject(fn ($name): bool => $statuses->has($name));
        if ($missingStatuses->isNotEmpty()) {
            throw ValidationException::withMessages([
                'backup' => 'Faltan estados necesarios para restaurar la categoría: ' . $missingStatuses->implode(', '),
            ]);
        }

        $this->validateReferencedRoles($categoryData['permissions'] ?? []);
        $users = $this->resolveReferencedUsers($payload);

        return DB::transaction(function () use ($payload, $categoryData, $slug, $statuses, $users): ForumCategory {
            $category = ForumCategory::create([
                'slug' => $slug,
                'title' => (string) $categoryData['title'],
                'singular' => $categoryData['singular'] ?? null,
                'description' => $categoryData['description'] ?? null,
                'hint' => $categoryData['hint'] ?? null,
                'icon' => $categoryData['icon'] ?? '💬',
                'color' => $categoryData['color'] ?? '#38bdf8',
                'channel' => 'personal',
                'system_type' => ForumCategory::TYPE_STANDARD,
                'process_type' => $categoryData['process_type'] ?? null,
                'is_system' => false,
                'is_enabled' => (bool) ($categoryData['is_enabled'] ?? true),
                'allow_polls' => (bool) ($categoryData['allow_polls'] ?? false),
                'sort_order' => (int) ($categoryData['sort_order'] ?? 100),
            ]);

            if (! empty($categoryData['created_at']) || ! empty($categoryData['updated_at'])) {
                DB::table('community_forum_categories')
                    ->where('id', $category->id)
                    ->update([
                        'created_at' => $categoryData['created_at'] ?? $category->created_at,
                        'updated_at' => $categoryData['updated_at'] ?? $category->updated_at,
                    ]);
                $category->refresh();
            }

            $category->statuses()->sync($statuses->pluck('id')->all());
            $this->restorePermissions($category, $categoryData['permissions'] ?? [], $users);

            foreach ($payload['posts'] ?? [] as $postData) {
                $this->importPost($category, $postData, $users);
            }

            return $category->fresh(['statuses']);
        });
    }

    public function deleteCategoryWithContent(ForumCategory $category): void
    {
        if ($category->isDiary()) {
            throw ValidationException::withMessages(['category' => 'Diario es una categoría interna y no se puede eliminar.']);
        }

        DB::transaction(function () use ($category): void {
            $postIds = DB::table('community_posts')
                ->where('forum_category_id', $category->id)
                ->pluck('id');

            $commentIds = DB::table('community_post_comments')
                ->whereIn('community_post_id', $postIds)
                ->pluck('id');

            $processIds = DB::table('community_posts')
                ->whereIn('id', $postIds)
                ->whereNotNull('community_process_id')
                ->pluck('community_process_id');

            $pollIds = DB::table('community_polls')
                ->whereIn('community_post_id', $postIds)
                ->orWhereIn('community_process_id', $processIds)
                ->pluck('id');

            if ($postIds->isNotEmpty()) {
                DB::table('community_post_reads')->whereIn('community_post_id', $postIds)->delete();
                DB::table('community_subscriptions')
                    ->where('subscribable_type', (new CommunityPost())->getMorphClass())
                    ->whereIn('subscribable_id', $postIds)
                    ->delete();
                DB::table('community_reactions')
                    ->where('reactable_type', (new CommunityPost())->getMorphClass())
                    ->whereIn('reactable_id', $postIds)
                    ->delete();
            }

            if ($commentIds->isNotEmpty()) {
                DB::table('community_reactions')
                    ->where('reactable_type', (new CommunityPostComment())->getMorphClass())
                    ->whereIn('reactable_id', $commentIds)
                    ->delete();
            }

            if ($pollIds->isNotEmpty()) {
                DB::table('community_poll_votes')->whereIn('community_poll_id', $pollIds)->delete();
                DB::table('community_poll_options')->whereIn('community_poll_id', $pollIds)->delete();
                DB::table('community_polls')->whereIn('id', $pollIds)->delete();
            }

            if ($processIds->isNotEmpty()) {
                DB::table('community_process_applications')->whereIn('community_process_id', $processIds)->delete();
            }

            DB::table('community_post_comments')->whereIn('community_post_id', $postIds)->delete();
            DB::table('community_posts')->whereIn('id', $postIds)->delete();

            if ($processIds->isNotEmpty()) {
                DB::table('community_processes')->whereIn('id', $processIds)->delete();
            }

            $category->delete();
        });
    }


    private function exportPermissions(ForumCategory $category): array
    {
        $resource = $category->permissionResource();
        if (! $resource) {
            return [];
        }

        $result = [];
        foreach (array_keys(ForumCategory::PERMISSION_ACTIONS) as $action) {
            $permission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', "{$resource}.{$action}")
                ->first();

            $result[$action] = [
                'roles' => $permission
                    ? $permission->roles()->pluck('name')->values()->all()
                    : [],
                'users' => $permission
                    ? $permission->users()->get()
                        ->map(fn (User $user): array => $this->userReference($user->id))
                        ->values()
                        ->all()
                    : [],
            ];
        }

        return $result;
    }

    private function restorePermissions(ForumCategory $category, array $snapshot, array $users): void
    {
        $category->ensurePermissions();
        $resource = $category->permissionResource();
        if (! $resource) {
            return;
        }

        foreach ($snapshot as $action => $assignment) {
            if (! array_key_exists($action, ForumCategory::PERMISSION_ACTIONS)) {
                continue;
            }

            $permission = Permission::query()
                ->where('guard_name', 'web')
                ->where('name', "{$resource}.{$action}")
                ->first();
            if (! $permission) {
                continue;
            }

            foreach ($assignment['roles'] ?? [] as $roleName) {
                $role = Role::query()->where('guard_name', 'web')->where('name', $roleName)->first();
                $role?->givePermissionTo($permission);
            }

            foreach ($assignment['users'] ?? [] as $reference) {
                $userId = $this->userId($reference, $users);
                if ($userId) {
                    User::withTrashed()->find($userId)?->givePermissionTo($permission);
                }
            }
        }
    }

    private function exportPost(object $post): array
    {
        $comments = DB::table('community_post_comments')
            ->where('community_post_id', $post->id)
            ->orderBy('id')
            ->get();

        $process = $post->community_process_id
            ? DB::table('community_processes')->where('id', $post->community_process_id)->first()
            : null;

        $poll = DB::table('community_polls')
            ->where('community_post_id', $post->id)
            ->first();

        return [
            'old_id' => $post->id,
            'author' => $this->userReference($post->user_id),
            'title' => $post->title,
            'body' => $post->body,
            'is_pinned' => (bool) $post->is_pinned,
            'is_locked' => (bool) $post->is_locked,
            'locked_at' => $post->locked_at,
            'locked_by' => $this->userReference($post->locked_by),
            'created_at' => $post->created_at,
            'updated_at' => $post->updated_at,
            'deleted_at' => $post->deleted_at,
            'process' => $process ? $this->exportProcess($process) : null,
            'poll' => $poll ? $this->exportPoll($poll) : null,
            'comments' => $comments->map(fn ($comment): array => [
                'old_id' => $comment->id,
                'author' => $this->userReference($comment->user_id),
                'body' => $comment->body,
                'created_at' => $comment->created_at,
                'updated_at' => $comment->updated_at,
                'deleted_at' => $comment->deleted_at,
                'reactions' => $this->exportReactions((new CommunityPostComment())->getMorphClass(), $comment->id),
            ])->values()->all(),
            'reactions' => $this->exportReactions((new CommunityPost())->getMorphClass(), $post->id),
            'subscriptions' => DB::table('community_subscriptions')
                ->where('subscribable_type', (new CommunityPost())->getMorphClass())
                ->where('subscribable_id', $post->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($subscription): array => [
                    'user' => $this->userReference($subscription->user_id),
                    'created_at' => $subscription->created_at,
                    'updated_at' => $subscription->updated_at,
                ])->values()->all(),
            'reads' => DB::table('community_post_reads')
                ->where('community_post_id', $post->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($read): array => [
                    'user' => $this->userReference($read->user_id),
                    'read_at' => $read->read_at,
                    'created_at' => $read->created_at,
                    'updated_at' => $read->updated_at,
                ])->values()->all(),
        ];
    }

    private function exportProcess(object $process): array
    {
        return [
            'old_id' => $process->id,
            'type' => $process->type,
            'title' => $process->title,
            'description' => $process->description,
            'status' => $process->status,
            'applications_enabled' => (bool) $process->applications_enabled,
            'applications_start_at' => $process->applications_start_at,
            'applications_end_at' => $process->applications_end_at,
            'allow_application_edit' => (bool) $process->allow_application_edit,
            'allow_application_withdraw' => (bool) $process->allow_application_withdraw,
            'max_winners' => $process->max_winners,
            'eligible_statuses' => $this->decodeJson($process->eligible_statuses),
            'created_by' => $this->userReference($process->created_by),
            'created_at' => $process->created_at,
            'updated_at' => $process->updated_at,
            'deleted_at' => $process->deleted_at,
            'applications' => DB::table('community_process_applications')
                ->where('community_process_id', $process->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($application): array => [
                    'user' => $this->userReference($application->user_id),
                    'body' => $application->body,
                    'withdrawn_at' => $application->withdrawn_at,
                    'created_at' => $application->created_at,
                    'updated_at' => $application->updated_at,
                ])->values()->all(),
        ];
    }

    private function exportPoll(object $poll): array
    {
        $options = DB::table('community_poll_options')
            ->where('community_poll_id', $poll->id)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get();

        return [
            'old_id' => $poll->id,
            'title' => $poll->title,
            'description' => $poll->description,
            'is_published' => (bool) $poll->is_published,
            'selection_mode' => $poll->selection_mode,
            'min_choices' => $poll->min_choices,
            'max_choices' => $poll->max_choices,
            'allow_vote_change' => (bool) $poll->allow_vote_change,
            'is_anonymous' => (bool) $poll->is_anonymous,
            'results_visibility' => $poll->results_visibility,
            'show_voter_names' => (bool) $poll->show_voter_names,
            'show_participation' => (bool) $poll->show_participation,
            'allow_abstain' => (bool) $poll->allow_abstain,
            'randomize_options' => (bool) $poll->randomize_options,
            'quorum_percent' => $poll->quorum_percent,
            'starts_at' => $poll->starts_at,
            'ends_at' => $poll->ends_at,
            'created_by' => $this->userReference($poll->created_by),
            'created_at' => $poll->created_at,
            'updated_at' => $poll->updated_at,
            'deleted_at' => $poll->deleted_at,
            'options' => $options->map(fn ($option): array => [
                'old_id' => $option->id,
                'candidate' => $this->userReference($option->candidate_user_id),
                'label' => $option->label,
                'sort_order' => (int) $option->sort_order,
                'created_at' => $option->created_at,
                'updated_at' => $option->updated_at,
            ])->values()->all(),
            'votes' => DB::table('community_poll_votes')
                ->where('community_poll_id', $poll->id)
                ->orderBy('id')
                ->get()
                ->map(fn ($vote): array => [
                    'option_old_id' => $vote->community_poll_option_id,
                    'user' => $this->userReference($vote->user_id),
                    'is_abstain' => (bool) $vote->is_abstain,
                    'created_at' => $vote->created_at,
                    'updated_at' => $vote->updated_at,
                ])->values()->all(),
        ];
    }

    private function exportReactions(string $type, int $id): array
    {
        return DB::table('community_reactions')
            ->where('reactable_type', $type)
            ->where('reactable_id', $id)
            ->orderBy('id')
            ->get()
            ->map(fn ($reaction): array => [
                'user' => $this->userReference($reaction->user_id),
                'reaction' => $reaction->reaction,
                'created_at' => $reaction->created_at,
                'updated_at' => $reaction->updated_at,
            ])->values()->all();
    }

    private function userReference(?int $userId): ?array
    {
        if (! $userId) {
            return null;
        }

        $user = User::withTrashed()->find($userId);

        return $user ? ['id' => (int) $user->id, 'nick' => $user->nick] : ['id' => (int) $userId, 'nick' => null];
    }

    private function validateReferencedRoles(array $snapshot): void
    {
        $roleNames = collect($snapshot)
            ->flatMap(fn ($assignment): array => is_array($assignment) ? ($assignment['roles'] ?? []) : [])
            ->filter()
            ->unique()
            ->values();

        if ($roleNames->isEmpty()) {
            return;
        }

        $existing = Role::query()
            ->where('guard_name', 'web')
            ->whereIn('name', $roleNames)
            ->pluck('name');

        $missing = $roleNames->diff($existing)->values();
        if ($missing->isNotEmpty()) {
            throw ValidationException::withMessages([
                'backup' => 'No se puede restaurar porque faltan roles referenciados: ' . $missing->implode(', '),
            ]);
        }
    }

    private function resolveReferencedUsers(array $payload): array
    {
        $refs = [];
        $this->collectUserReferences($payload, $refs);
        $resolved = [];
        $missing = [];

        foreach ($refs as $key => $reference) {
            $user = null;
            if (! empty($reference['id'])) {
                $user = User::withTrashed()->find((int) $reference['id']);
                if ($user && filled($reference['nick']) && strcasecmp((string) $user->nick, (string) $reference['nick']) !== 0) {
                    $user = null;
                }
            }

            if (! $user && filled($reference['nick'])) {
                $user = User::withTrashed()
                    ->whereRaw('LOWER(nick) = ?', [Str::lower((string) $reference['nick'])])
                    ->first();
            }

            if (! $user) {
                $missing[] = $reference['nick'] ?: ('ID ' . $reference['id']);
                continue;
            }

            $resolved[$key] = (int) $user->id;
        }

        if ($missing !== []) {
            throw ValidationException::withMessages([
                'backup' => 'No se puede restaurar porque faltan usuarios referenciados: ' . implode(', ', array_unique($missing)),
            ]);
        }

        return $resolved;
    }

    private function collectUserReferences(mixed $value, array &$refs): void
    {
        if (! is_array($value)) {
            return;
        }

        if (array_key_exists('id', $value) && array_key_exists('nick', $value) && count($value) <= 2) {
            if ($value['id'] || filled($value['nick'])) {
                $refs[$this->userReferenceKey($value)] = $value;
            }
            return;
        }

        foreach ($value as $child) {
            $this->collectUserReferences($child, $refs);
        }
    }

    private function importPost(ForumCategory $category, array $data, array $users): void
    {
        $processId = null;
        if (! empty($data['process'])) {
            $processId = $this->importProcess($data['process'], $users);
        }

        $postId = DB::table('community_posts')->insertGetId([
            'channel' => 'personal',
            'community_process_id' => $processId,
            'forum_category_id' => $category->id,
            'user_id' => $this->userId($data['author'] ?? null, $users),
            'title' => (string) $data['title'],
            'body' => (string) $data['body'],
            'is_pinned' => (bool) ($data['is_pinned'] ?? false),
            'is_locked' => (bool) ($data['is_locked'] ?? false),
            'locked_at' => $data['locked_at'] ?? null,
            'locked_by' => $this->userId($data['locked_by'] ?? null, $users),
            'created_at' => $data['created_at'] ?? now(),
            'updated_at' => $data['updated_at'] ?? now(),
            'deleted_at' => $data['deleted_at'] ?? null,
        ]);

        if (! empty($data['poll'])) {
            $this->importPoll($data['poll'], $postId, $processId, $users);
        }

        foreach ($data['comments'] ?? [] as $comment) {
            $commentId = DB::table('community_post_comments')->insertGetId([
                'community_post_id' => $postId,
                'user_id' => $this->userId($comment['author'] ?? null, $users),
                'body' => (string) $comment['body'],
                'created_at' => $comment['created_at'] ?? now(),
                'updated_at' => $comment['updated_at'] ?? now(),
                'deleted_at' => $comment['deleted_at'] ?? null,
            ]);
            $this->importReactions($comment['reactions'] ?? [], (new CommunityPostComment())->getMorphClass(), $commentId, $users);
        }

        $this->importReactions($data['reactions'] ?? [], (new CommunityPost())->getMorphClass(), $postId, $users);

        foreach ($data['subscriptions'] ?? [] as $subscription) {
            DB::table('community_subscriptions')->insertOrIgnore([
                'user_id' => $this->userId($subscription['user'] ?? null, $users),
                'subscribable_type' => (new CommunityPost())->getMorphClass(),
                'subscribable_id' => $postId,
                'created_at' => $subscription['created_at'] ?? now(),
                'updated_at' => $subscription['updated_at'] ?? now(),
            ]);
        }

        foreach ($data['reads'] ?? [] as $read) {
            DB::table('community_post_reads')->insertOrIgnore([
                'community_post_id' => $postId,
                'user_id' => $this->userId($read['user'] ?? null, $users),
                'read_at' => $read['read_at'] ?? now(),
                'created_at' => $read['created_at'] ?? now(),
                'updated_at' => $read['updated_at'] ?? now(),
            ]);
        }
    }

    private function importProcess(array $data, array $users): int
    {
        $processId = DB::table('community_processes')->insertGetId([
            'type' => (string) $data['type'],
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'status' => (string) ($data['status'] ?? 'discussion'),
            'applications_enabled' => (bool) ($data['applications_enabled'] ?? false),
            'applications_start_at' => $data['applications_start_at'] ?? null,
            'applications_end_at' => $data['applications_end_at'] ?? null,
            'allow_application_edit' => (bool) ($data['allow_application_edit'] ?? true),
            'allow_application_withdraw' => (bool) ($data['allow_application_withdraw'] ?? true),
            'max_winners' => $data['max_winners'] ?? null,
            'eligible_statuses' => json_encode($data['eligible_statuses'] ?? ['ACTIVO'], JSON_UNESCAPED_UNICODE),
            'created_by' => $this->userId($data['created_by'] ?? null, $users),
            'created_at' => $data['created_at'] ?? now(),
            'updated_at' => $data['updated_at'] ?? now(),
            'deleted_at' => $data['deleted_at'] ?? null,
        ]);

        foreach ($data['applications'] ?? [] as $application) {
            DB::table('community_process_applications')->insert([
                'community_process_id' => $processId,
                'user_id' => $this->userId($application['user'] ?? null, $users),
                'body' => (string) $application['body'],
                'withdrawn_at' => $application['withdrawn_at'] ?? null,
                'created_at' => $application['created_at'] ?? now(),
                'updated_at' => $application['updated_at'] ?? now(),
            ]);
        }

        return $processId;
    }

    private function importPoll(array $data, int $postId, ?int $processId, array $users): void
    {
        $pollId = DB::table('community_polls')->insertGetId([
            'community_process_id' => $processId,
            'community_post_id' => $postId,
            'title' => (string) $data['title'],
            'description' => $data['description'] ?? null,
            'is_published' => (bool) ($data['is_published'] ?? false),
            'selection_mode' => $data['selection_mode'] ?? 'single',
            'min_choices' => $data['min_choices'] ?? 1,
            'max_choices' => $data['max_choices'] ?? 1,
            'allow_vote_change' => (bool) ($data['allow_vote_change'] ?? true),
            'is_anonymous' => (bool) ($data['is_anonymous'] ?? false),
            'results_visibility' => $data['results_visibility'] ?? 'always',
            'show_voter_names' => (bool) ($data['show_voter_names'] ?? false),
            'show_participation' => (bool) ($data['show_participation'] ?? true),
            'allow_abstain' => (bool) ($data['allow_abstain'] ?? false),
            'randomize_options' => (bool) ($data['randomize_options'] ?? false),
            'quorum_percent' => $data['quorum_percent'] ?? null,
            'starts_at' => $data['starts_at'] ?? null,
            'ends_at' => $data['ends_at'] ?? null,
            'created_by' => $this->userId($data['created_by'] ?? null, $users),
            'created_at' => $data['created_at'] ?? now(),
            'updated_at' => $data['updated_at'] ?? now(),
            'deleted_at' => $data['deleted_at'] ?? null,
        ]);

        $optionMap = [];
        foreach ($data['options'] ?? [] as $option) {
            $newId = DB::table('community_poll_options')->insertGetId([
                'community_poll_id' => $pollId,
                'candidate_user_id' => $this->userId($option['candidate'] ?? null, $users),
                'label' => (string) $option['label'],
                'sort_order' => (int) ($option['sort_order'] ?? 10),
                'created_at' => $option['created_at'] ?? now(),
                'updated_at' => $option['updated_at'] ?? now(),
            ]);
            if (! empty($option['old_id'])) {
                $optionMap[(int) $option['old_id']] = $newId;
            }
        }

        foreach ($data['votes'] ?? [] as $vote) {
            $oldOptionId = $vote['option_old_id'] ?? null;
            DB::table('community_poll_votes')->insert([
                'community_poll_id' => $pollId,
                'community_poll_option_id' => $oldOptionId ? ($optionMap[(int) $oldOptionId] ?? null) : null,
                'user_id' => $this->userId($vote['user'] ?? null, $users),
                'is_abstain' => (bool) ($vote['is_abstain'] ?? false),
                'created_at' => $vote['created_at'] ?? now(),
                'updated_at' => $vote['updated_at'] ?? now(),
            ]);
        }
    }

    private function importReactions(array $reactions, string $type, int $id, array $users): void
    {
        foreach ($reactions as $reaction) {
            DB::table('community_reactions')->insert([
                'user_id' => $this->userId($reaction['user'] ?? null, $users),
                'reactable_type' => $type,
                'reactable_id' => $id,
                'reaction' => (string) $reaction['reaction'],
                'created_at' => $reaction['created_at'] ?? now(),
                'updated_at' => $reaction['updated_at'] ?? now(),
            ]);
        }
    }

    private function userId(?array $reference, array $users): ?int
    {
        if (! $reference) {
            return null;
        }

        return $users[$this->userReferenceKey($reference)] ?? null;
    }

    private function userReferenceKey(array $reference): string
    {
        return (string) ($reference['id'] ?? '') . '|' . Str::lower((string) ($reference['nick'] ?? ''));
    }

    private function validatePayload(array $payload): void
    {
        if (($payload['format'] ?? null) !== self::FORMAT || (int) ($payload['version'] ?? 0) !== self::VERSION) {
            throw ValidationException::withMessages(['backup' => 'El archivo no es una copia de categoría compatible con esta versión de Squad ALPHA.']);
        }

        if (! is_array($payload['category'] ?? null) || blank(Arr::get($payload, 'category.slug')) || blank(Arr::get($payload, 'category.title'))) {
            throw ValidationException::withMessages(['backup' => 'La copia no contiene una categoría válida.']);
        }

        $processType = Arr::get($payload, 'category.process_type');
        if (! in_array($processType, [null, 'convocatoria', 'propuestas', 'consulta'], true)) {
            throw ValidationException::withMessages(['backup' => 'La copia contiene un flujo de categoría no compatible.']);
        }

        if (! is_array($payload['posts'] ?? [])) {
            throw ValidationException::withMessages(['backup' => 'La lista de hilos de la copia no es válida.']);
        }
    }

    private function decodeJson(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (! is_string($value) || $value === '') {
            return [];
        }

        $decoded = json_decode($value, true);

        return is_array($decoded) ? $decoded : [];
    }
}
