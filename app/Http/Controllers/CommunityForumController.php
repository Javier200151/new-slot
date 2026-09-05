<?php

namespace App\Http\Controllers;

use App\Models\CommunityPoll;
use App\Models\CommunityDiary;
use App\Models\CommunityDiaryComment;
use App\Models\CommunityPost;
use App\Models\CommunityPostComment;
use App\Models\CommunityReaction;
use App\Models\User;
use App\Models\CommunityProcess;
use App\Services\CommunityPollManager;
use App\Services\CommunityPollViewService;
use App\Services\CommunitySubscriptionService;
use App\Support\CommunityArea;
use App\Support\CommunityForumCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CommunityForumController extends Controller
{
    public function forum(Request $request): View
    {
        $this->authorizeForumHome($request);

        return $this->personalLanding($request);
    }

    public function index(Request $request, string $channel): View
    {
        $this->authorizeChannel($request, $channel);

        if ($channel === 'personal') {
            return $this->personalLanding($request);
        }

        return $this->forumList($request, 'cantina', CommunityForumCategory::CANTINA);
    }

    public function category(Request $request, string $category): View
    {
        $this->authorizeChannel($request, 'personal');

        $definition = CommunityForumCategory::get($category);
        abort_unless($definition && ($definition['channel'] ?? null) === 'personal', 404);
        abort_unless(CommunityForumCategory::canView($request->user(), $category), 403, 'No tienes acceso a esta categoría.');

        return $this->forumList($request, 'personal', $category);
    }

    public function unread(Request $request): View
    {
        $this->authorizeForumHome($request);

        $user = $request->user();
        $query = $this->visibleForumPostQuery($user)
            ->with([
                'author.status',
                'author.mainSqaGroup',
                'forumCategory',
                'process.poll',
                'process.activeApplications',
                'poll',
            ])
            ->withCount('comments')
            ->withExists([
                'subscriptions as is_subscribed' => fn ($subscriptions) =>
                    $subscriptions->where('user_id', $user->id),
            ])
            ->withReadStateFor($user)
            ->unreadFor($user);

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas(
                        'author',
                        fn ($author) => $author->where('nick', 'like', "%{$search}%"),
                    );
            });
        }

        $filter = (string) $request->query('filtro', 'all');
        match ($filter) {
            'poll' => $query->whereHas('poll'),
            'locked' => $query->where('is_locked', true),
            default => null,
        };

        $posts = $query
            ->latest('updated_at')
            ->paginate(20)
            ->withQueryString();

        return view('community.forum.index', [
            'channel' => 'unread',
            'channelTitle' => 'Nuevos mensajes',
            'channelDescription' => 'Hilos nuevos o actualizados que todavía no has abierto desde su última actividad.',
            'categories' => collect(),
            'category' => null,
            'categoryKey' => null,
            'posts' => $posts,
            'search' => $search,
            'filter' => $filter,
            'canCreate' => false,
            'canModerate' => false,
            'canDeleteAny' => false,
            'forumUnreadCount' => $this->unreadForumCount($user),
            'forumUnreadBaseline' => $user->forum_unread_baseline_at,
            'isUnreadView' => true,
        ]);
    }

    public function show(Request $request, string $channel, CommunityPost $post): View
    {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);

        $post->load([
            'forumCategory',
            'author.status',
            'author.mainSqaGroup',
            'reactions.user:id,nick',
            'lockedBy:id,nick',
            'comments.author.status',
            'comments.author.mainSqaGroup',
            'comments.reactions.user:id,nick',
            'process.poll.options',
            'process.activeApplications.user.status',
            'process.activeApplications.user.mainSqaGroup',
            'poll.options',
            'poll.process.post',
        ]);

        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(
            CommunityForumCategory::canView($request->user(), $categoryKey),
            403,
            'No tienes acceso a esta categoría.',
        );

        $post->markReadBy($request->user());

        $authors = collect([$post->author])
            ->merge($post->comments->pluck('author'))
            ->filter()
            ->unique('id')
            ->values();

        $this->hydrateAuthorActivity($authors);

        $process = $post->process;
        $myApplication = null;

        if ($process) {
            $myApplication = $process->applications()
                ->where('user_id', $request->user()->id)
                ->first();
        }

        $pollData = $post->poll
            ? app(CommunityPollViewService::class)->forPoll($post->poll, $request->user())
            : null;

        $category = CommunityForumCategory::get($categoryKey);
        $canModerate = $this->canModerate($request, $categoryKey);
        $canDeleteAny = $this->canDeleteAny($request, $categoryKey);
        $isSubscribed = $post->subscriptions()
            ->where('user_id', $request->user()->id)
            ->exists();

        return view('community.forum.show', [
            'channel' => $channel,
            'channelTitle' => $this->channelTitle($channel),
            'categoryKey' => $categoryKey,
            'category' => $category,
            'post' => $post,
            'process' => $process,
            'myApplication' => $myApplication,
            'pollData' => $pollData,
            'isSubscribed' => $isSubscribed,
            'canVote' => CommunityArea::can($request->user(), CommunityArea::POLLS),
            'canReply' => CommunityForumCategory::can($request->user(), $categoryKey, 'reply'),
            'canModerate' => $canModerate,
            'canDeleteAny' => $canDeleteAny,
            'canManageThread' => $post->user_id === $request->user()->id || $request->user()->hasRole('admin'),
            'canManageProcess' => $process && (
                $post->user_id === $request->user()->id
                || $process->created_by === $request->user()->id
                || $canModerate
            ),
            'canCreatePoll' => $channel === 'personal'
                && ! $post->poll
                && CommunityForumCategory::can($request->user(), $categoryKey, 'poll')
                && ($post->user_id === $request->user()->id || $canModerate),
        ]);
    }

    public function store(
        Request $request,
        string $channel,
        CommunityPollManager $pollManager,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($channel === 'cantina', 404);

        return $this->storeThread(
            $request,
            $channel,
            CommunityForumCategory::CANTINA,
            $pollManager,
        );
    }

    public function storeCategory(
        Request $request,
        string $category,
        CommunityPollManager $pollManager,
    ): RedirectResponse {
        $this->authorizeChannel($request, 'personal');

        $definition = CommunityForumCategory::get($category);
        abort_unless($definition && ($definition['channel'] ?? null) === 'personal', 404);
        abort_unless(CommunityForumCategory::canView($request->user(), $category), 403, 'No tienes acceso a esta categoría.');

        return $this->storeThread($request, 'personal', $category, $pollManager);
    }

    public function update(
        Request $request,
        string $channel,
        CommunityPost $post,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403);
        abort_unless(
            $post->user_id === $request->user()->id || $request->user()->hasRole('admin'),
            403
        );

        $validated = $request->validate([
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'body' => ['required', 'string', 'min:2', 'max:30000'],
        ]);

        DB::transaction(function () use ($post, $validated): void {
            $post->update($validated);

            if ($post->process) {
                $post->process->update([
                    'title' => $validated['title'],
                    'description' => $validated['body'],
                ]);
            }
        });

        $subscriptions->notifyPost($post, $request->user(), 'thread_updated');

        return back()->with('status', 'post-updated');
    }

    public function destroy(Request $request, string $channel, CommunityPost $post): RedirectResponse
    {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);

        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403);
        abort_unless(
            $post->user_id === $request->user()->id || $this->canDeleteAny($request, $categoryKey),
            403
        );

        $post->delete();

        $route = $channel === 'personal'
            ? route('community.forum.category', $categoryKey)
            : route('community.forum.index', 'cantina');

        return redirect($route)->with('status', 'post-deleted');
    }

    public function toggleLock(Request $request, string $channel, CommunityPost $post): RedirectResponse
    {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless($this->canModerate($request, $categoryKey), 403);

        $lock = ! $post->is_locked;

        $post->update([
            'is_locked' => $lock,
            'locked_at' => $lock ? now() : null,
            'locked_by' => $lock ? $request->user()->id : null,
        ]);

        return back()->with('status', $lock ? 'thread-locked' : 'thread-reopened');
    }

    public function togglePin(Request $request, string $channel, CommunityPost $post): RedirectResponse
    {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless($this->canModerate($request, $categoryKey), 403);

        $post->update(['is_pinned' => ! $post->is_pinned]);

        return back()->with('status', $post->is_pinned ? 'thread-pinned' : 'thread-unpinned');
    }

    public function reactToPost(
        Request $request,
        string $channel,
        CommunityPost $post,
    ): RedirectResponse|JsonResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);

        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(
            CommunityForumCategory::canView($request->user(), $categoryKey),
            403,
            'No tienes acceso a esta categoría.'
        );

        return $this->toggleReaction(
            $request,
            $post,
            route('community.forum.show', [$channel, $post]) . '#mensaje-inicial',
        );
    }

    public function reactToComment(
        Request $request,
        string $channel,
        CommunityPost $post,
        CommunityPostComment $comment,
    ): RedirectResponse|JsonResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        abort_unless($comment->community_post_id === $post->id, 404);

        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(
            CommunityForumCategory::canView($request->user(), $categoryKey),
            403,
            'No tienes acceso a esta categoría.'
        );

        return $this->toggleReaction(
            $request,
            $comment,
            route('community.forum.show', [$channel, $post]) . '#respuesta-' . $comment->id,
        );
    }

    public function comment(
        Request $request,
        string $channel,
        CommunityPost $post,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);

        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(
            CommunityForumCategory::can($request->user(), $categoryKey, 'reply'),
            403,
            'Tu rol no puede responder en esta categoría.'
        );

        if ($post->is_locked) {
            return back()->with('status', 'thread-locked');
        }

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:20000'],
        ]);

        CommunityPostComment::create([
            'community_post_id' => $post->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $post->touch();
        $subscriptions->notifyPost($post, $request->user(), 'new_reply');

        return redirect()
            ->to(route('community.forum.show', [$channel, $post]) . '#respuestas')
            ->with('status', 'comment-created');
    }

    public function updateComment(
        Request $request,
        string $channel,
        CommunityPost $post,
        CommunityPostComment $comment,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        abort_unless($comment->community_post_id === $post->id, 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403);
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->hasRole('admin'),
            403
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:20000'],
        ]);

        $comment->update(['body' => $validated['body']]);
        $post->touch();
        $subscriptions->notifyPost($post, $request->user(), 'reply_updated');

        return back()->with('status', 'comment-updated');
    }

    public function destroyComment(
        Request $request,
        string $channel,
        CommunityPost $post,
        CommunityPostComment $comment,
    ): RedirectResponse {
        $this->authorizeChannel($request, $channel);
        abort_unless($post->channel === $channel, 404);
        abort_unless($comment->community_post_id === $post->id, 404);
        $categoryKey = CommunityForumCategory::keyForPost($post);
        abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403);
        abort_unless(
            $comment->user_id === $request->user()->id || $this->canDeleteAny($request, $categoryKey),
            403
        );

        $comment->delete();
        $post->touch();

        return back()->with('status', 'comment-deleted');
    }

    private function toggleReaction(
        Request $request,
        CommunityPost|CommunityPostComment $reactable,
        string $redirectUrl,
    ): RedirectResponse|JsonResponse {
        $validated = $request->validate([
            'reaction' => [
                'required',
                'string',
                Rule::in(array_keys(CommunityReaction::options())),
            ],
        ]);

        $userId = $request->user()->id;
        $reactionCode = $validated['reaction'];

        DB::transaction(function () use ($reactable, $userId, $reactionCode): void {
            $existing = $reactable->reactions()
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();

            if ($existing?->reaction === $reactionCode) {
                $existing->delete();

                return;
            }

            if ($existing) {
                $existing->update(['reaction' => $reactionCode]);

                return;
            }

            $reactable->reactions()->create([
                'user_id' => $userId,
                'reaction' => $reactionCode,
            ]);
        });

        $reactable->load('reactions.user:id,nick');

        if ($request->expectsJson()) {
            $options = CommunityReaction::options();
            $mine = $reactable->reactions->firstWhere('user_id', $userId)?->reaction;
            $counts = [];
            $reactors = [];

            foreach ($options as $code => $option) {
                $matching = $reactable->reactions->where('reaction', $code);
                $counts[$code] = $matching->count();
                $reactors[$code] = $matching
                    ->pluck('user.nick')
                    ->filter()
                    ->values()
                    ->all();
            }

            return response()->json([
                'mine' => $mine,
                'counts' => $counts,
                'reactors' => $reactors,
            ]);
        }

        return redirect($redirectUrl)->with('status', 'reaction-updated');
    }

    private function personalLanding(Request $request): View
    {
        $user = $request->user();

        $categories = collect(CommunityForumCategory::landing())
            ->filter(
                fn (array $category, string $key): bool =>
                    CommunityForumCategory::canView($user, $key)
            )
            ->map(function (array $category, string $key) use ($request, $user): array {
                if ($key === CommunityForumCategory::DIARY) {
                    $lastDiary = CommunityDiary::query()
                        ->with('author:id,nick')
                        ->latest('updated_at')
                        ->first();
                    $myDiaryExists = CommunityDiary::query()
                        ->where('user_id', $request->user()->id)
                        ->exists();
                    $canStartDiary = $request->user()->hasRole('admin')
                        || in_array(
                            CommunityArea::status($request->user()),
                            ['RECLUTA', 'ACTIVO'],
                            true,
                        );

                    return [
                        ...$category,
                        'url' => route('community.diary.index'),
                        'threads_count' => CommunityDiary::query()->count(),
                        'replies_count' => CommunityDiaryComment::query()->count(),
                        'last_activity' => $lastDiary?->updated_at,
                        'last_title' => $lastDiary
                            ? 'Diario de ' . ($lastDiary->author?->nick ?: $lastDiary->author_nick)
                            : null,
                        'can_create' => $myDiaryExists || $canStartDiary,
                        'unread_count' => 0,
                    ];
                }

                $query = CommunityForumCategory::applyToQuery(CommunityPost::query(), $key);
                $lastPost = (clone $query)
                    ->with('author:id,nick')
                    ->latest('updated_at')
                    ->first();

                return [
                    ...$category,
                    'url' => ($category['channel'] ?? 'personal') === 'cantina'
                        ? route('community.forum.index', 'cantina')
                        : route('community.forum.category', $key),
                    'threads_count' => (clone $query)->count(),
                    'replies_count' => CommunityPostComment::query()
                        ->whereHas('post', fn ($post) => CommunityForumCategory::applyToQuery($post, $key))
                        ->count(),
                    'last_activity' => $lastPost?->updated_at,
                    'last_title' => $lastPost?->title,
                    'can_create' => CommunityForumCategory::can($request->user(), $key, 'create'),
                    'unread_count' => (clone $query)->unreadFor($user)->count(),
                ];
            });

        $forumUnreadCount = (int) $categories->sum('unread_count');

        return view('community.forum.index', [
            'channel' => 'personal',
            'channelTitle' => 'Foro',
            'channelDescription' => $this->channelDescription('personal'),
            'categories' => $categories,
            'category' => null,
            'categoryKey' => null,
            'posts' => null,
            'search' => '',
            'filter' => 'all',
            'canCreate' => false,
            'canModerate' => false,
            'canDeleteAny' => false,
            'forumUnreadCount' => $forumUnreadCount,
            'forumUnreadBaseline' => $user->forum_unread_baseline_at,
            'isUnreadView' => false,
        ]);
    }

    private function forumList(Request $request, string $channel, string $categoryKey): View
    {
        $user = $request->user();
        $category = CommunityForumCategory::get($categoryKey);
        abort_unless($category, 404);
        abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403, 'No tienes acceso a esta categoría.');

        $query = CommunityForumCategory::applyToQuery(
            CommunityPost::query()
                ->with([
                    'author.status',
                    'author.mainSqaGroup',
                    'process.poll',
                    'process.activeApplications',
                    'poll',
                ])
                ->withCount('comments')
                ->withExists([
                    'subscriptions as is_subscribed' => fn ($subscriptions) =>
                        $subscriptions->where('user_id', $user->id),
                ])
                ->withReadStateFor($user),
            $categoryKey,
        );

        $search = trim((string) $request->query('q', ''));
        if ($search !== '') {
            $query->where(function ($builder) use ($search): void {
                $builder
                    ->where('title', 'like', "%{$search}%")
                    ->orWhere('body', 'like', "%{$search}%")
                    ->orWhereHas('author', fn ($author) => $author->where('nick', 'like', "%{$search}%"));
            });
        }

        $filter = (string) $request->query('filtro', 'all');
        match ($filter) {
            'poll' => $query->whereHas('poll'),
            'locked' => $query->where('is_locked', true),
            default => null,
        };

        $posts = $query
            ->orderByDesc('is_pinned')
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('community.forum.index', [
            'channel' => $channel,
            'channelTitle' => $this->channelTitle($channel),
            'channelDescription' => $this->channelDescription($channel),
            'categories' => $channel === 'personal'
                ? collect(CommunityForumCategory::personal())
                    ->filter(fn (array $item, string $key): bool => CommunityForumCategory::canView($request->user(), $key))
                : collect(),
            'category' => $category,
            'categoryKey' => $categoryKey,
            'posts' => $posts,
            'search' => $search,
            'filter' => $filter,
            'canCreate' => CommunityForumCategory::can($request->user(), $categoryKey, 'create'),
            'canModerate' => $this->canModerate($request, $categoryKey),
            'canDeleteAny' => $this->canDeleteAny($request, $categoryKey),
            'forumUnreadCount' => $this->unreadForumCount($user),
            'forumUnreadBaseline' => $user->forum_unread_baseline_at,
            'isUnreadView' => false,
        ]);
    }

    private function storeThread(
        Request $request,
        string $channel,
        string $categoryKey,
        CommunityPollManager $pollManager,
    ): RedirectResponse {
        abort_unless(CommunityForumCategory::can($request->user(), $categoryKey, 'create'), 403, 'Tu rol no puede publicar nuevos hilos en esta categoría.');

        $validated = $request->validate($this->threadRules($categoryKey));
        $category = CommunityForumCategory::get($categoryKey);
        abort_unless($category && ($category['channel'] ?? null) === $channel, 404);

        if ($channel === 'personal' && $request->boolean('poll_enabled')) {
            abort_unless(
                CommunityForumCategory::can($request->user(), $categoryKey, 'poll'),
                403,
                'Tu rol no puede crear votaciones en esta categoría.'
            );
        }

        $post = DB::transaction(function () use ($request, $channel, $validated, $category, $pollManager): CommunityPost {
            $process = null;
            $processType = $category['process_type'] ?? null;

            if ($channel === 'personal' && $processType) {
                $applicationsEnabled = $processType === CommunityProcess::TYPE_CALL
                    && $request->boolean('process_applications_enabled');

                $process = CommunityProcess::create([
                    'type' => $processType,
                    'title' => $validated['title'],
                    'description' => $validated['body'],
                    'status' => CommunityProcess::statusForNewProcess(
                        $applicationsEnabled,
                        $validated['process_applications_start_at'] ?? null,
                        $validated['process_applications_end_at'] ?? null,
                    ),
                    'applications_enabled' => $applicationsEnabled,
                    'applications_start_at' => $validated['process_applications_start_at'] ?? null,
                    'applications_end_at' => $validated['process_applications_end_at'] ?? null,
                    'allow_application_edit' => $request->boolean('process_allow_application_edit'),
                    'allow_application_withdraw' => $request->boolean('process_allow_application_withdraw'),
                    'max_winners' => $validated['process_max_winners'] ?? null,
                    'eligible_statuses' => $validated['process_eligible_statuses'] ?? ['ACTIVO'],
                    'created_by' => $request->user()->id,
                ]);
            }

            $post = CommunityPost::create([
                'channel' => $channel,
                'community_process_id' => $process?->id,
                'forum_category_id' => $category['id'] ?? null,
                'user_id' => $request->user()->id,
                'title' => $validated['title'],
                'body' => $validated['body'],
            ]);

            if ($channel === 'personal' && $request->boolean('poll_enabled')) {
                $pollManager->createForPost(
                    $post,
                    $request->user(),
                    $this->pollPayload($request, $validated),
                );
            }

            return $post;
        });

        $post->subscriptions()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('community.forum.show', [$channel, $post])
            ->with('status', 'post-created');
    }

    private function threadRules(string $categoryKey): array
    {
        $rules = [
            'title' => ['required', 'string', 'min:3', 'max:180'],
            'body' => ['required', 'string', 'min:2', 'max:30000'],
        ];

        if ($categoryKey === CommunityForumCategory::CANTINA) {
            return $rules;
        }

        return [
            ...$rules,
            'process_applications_enabled' => ['nullable', 'boolean'],
            'process_applications_start_at' => ['nullable', 'date'],
            'process_applications_end_at' => ['nullable', 'date', 'after:process_applications_start_at'],
            'process_allow_application_edit' => ['nullable', 'boolean'],
            'process_allow_application_withdraw' => ['nullable', 'boolean'],
            'process_max_winners' => ['nullable', 'integer', 'min:1', 'max:20'],
            'process_eligible_statuses' => ['nullable', 'array', 'max:3'],
            'process_eligible_statuses.*' => [Rule::in(['ACTIVO', 'RESERVA', 'RECLUTA'])],
            'poll_enabled' => ['nullable', 'boolean'],
            ...$this->pollRules(),
        ];
    }

    private function pollRules(): array
    {
        return [
            'poll_title' => ['nullable', 'string', 'max:180'],
            'poll_description' => ['nullable', 'string', 'max:5000'],
            'poll_options' => ['nullable', 'string', 'max:6000'],
            'poll_selection_mode' => ['nullable', Rule::in([CommunityPoll::MODE_SINGLE, CommunityPoll::MODE_MULTIPLE])],
            'poll_min_choices' => ['nullable', 'integer', 'min:1', 'max:30'],
            'poll_max_choices' => ['nullable', 'integer', 'min:1', 'max:30'],
            'poll_allow_vote_change' => ['nullable', 'boolean'],
            'poll_is_anonymous' => ['nullable', 'boolean'],
            'poll_results_visibility' => ['nullable', Rule::in([
                CommunityPoll::RESULTS_ALWAYS,
                CommunityPoll::RESULTS_AFTER_VOTE,
                CommunityPoll::RESULTS_AFTER_CLOSE,
                CommunityPoll::RESULTS_HIDDEN,
            ])],
            'poll_show_voter_names' => ['nullable', 'boolean'],
            'poll_show_participation' => ['nullable', 'boolean'],
            'poll_allow_abstain' => ['nullable', 'boolean'],
            'poll_randomize_options' => ['nullable', 'boolean'],
            'poll_quorum_percent' => ['nullable', 'integer', 'min:1', 'max:100'],
            'poll_starts_at' => ['nullable', 'date'],
            'poll_ends_at' => ['nullable', 'date', 'after:poll_starts_at'],
        ];
    }

    private function pollPayload(Request $request, array $validated): array
    {
        return [
            ...$validated,
            'poll_allow_vote_change' => $request->boolean('poll_allow_vote_change'),
            'poll_is_anonymous' => $request->boolean('poll_is_anonymous'),
            'poll_show_voter_names' => $request->boolean('poll_show_voter_names'),
            'poll_show_participation' => $request->boolean('poll_show_participation'),
            'poll_allow_abstain' => $request->boolean('poll_allow_abstain'),
            'poll_randomize_options' => $request->boolean('poll_randomize_options'),
        ];
    }

    private function hydrateAuthorActivity(Collection $authors): void
    {
        $ids = $authors->pluck('id')->filter()->values();

        if ($ids->isEmpty()) {
            return;
        }

        $postCounts = DB::table('community_posts')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $diaryEntryCounts = DB::table('community_diary_entries')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $commentCounts = DB::table('community_post_comments')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        $diaryCommentCounts = DB::table('community_diary_comments')
            ->select('user_id', DB::raw('COUNT(*) as total'))
            ->whereNull('deleted_at')
            ->whereIn('user_id', $ids)
            ->groupBy('user_id')
            ->pluck('total', 'user_id');

        foreach ($authors as $author) {
            $author->setAttribute(
                'community_posts_count',
                (int) ($postCounts[$author->id] ?? 0) + (int) ($diaryEntryCounts[$author->id] ?? 0)
            );
            $author->setAttribute(
                'community_comments_count',
                (int) ($commentCounts[$author->id] ?? 0) + (int) ($diaryCommentCounts[$author->id] ?? 0)
            );
        }
    }

    private function visibleForumPostQuery(User $user): Builder
    {
        $categoryKeys = collect(CommunityForumCategory::landing())
            ->filter(
                fn (array $category, string $key): bool =>
                    ($category['channel'] ?? null) !== 'diary'
                    && CommunityForumCategory::canView($user, $key),
            )
            ->keys()
            ->values();

        $query = CommunityPost::query();

        if ($categoryKeys->isEmpty()) {
            return $query->whereRaw('1 = 0');
        }

        return $query->where(
            function (Builder $visible) use ($categoryKeys): void {
                foreach ($categoryKeys as $key) {
                    $visible->orWhere(
                        function (Builder $categoryQuery) use ($key): void {
                            CommunityForumCategory::applyToQuery(
                                $categoryQuery,
                                (string) $key,
                            );
                        },
                    );
                }
            },
        );
    }

    private function unreadForumCount(User $user): int
    {
        return $this->visibleForumPostQuery($user)
            ->unreadFor($user)
            ->count();
    }

    private function canModerate(Request $request, string $categoryKey): bool
    {
        return CommunityForumCategory::can($request->user(), $categoryKey, 'moderate');
    }

    private function canDeleteAny(Request $request, string $categoryKey): bool
    {
        return CommunityForumCategory::can($request->user(), $categoryKey, 'delete');
    }

    private function authorizeChannel(Request $request, string $channel): void
    {
        $section = match ($channel) {
            'cantina' => CommunityArea::CANTINA,
            'personal' => CommunityArea::FORUM,
            default => null,
        };

        abort_unless(
            $section && CommunityArea::can($request->user(), $section),
            403,
            'No tienes acceso a este foro.'
        );
    }

    private function authorizeForumHome(Request $request): void
    {
        abort_unless(
            CommunityArea::hasArea($request->user()),
            403,
            'No tienes acceso al foro.',
        );
    }

    private function channelTitle(string $channel): string
    {
        return $channel === 'personal'
            ? 'Foro'
            : (CommunityForumCategory::get(CommunityForumCategory::CANTINA)['label'] ?? 'WHISKEY — Enguarrinando');
    }

    private function channelDescription(string $channel): string
    {
        return $channel === 'personal'
            ? 'Foro interno de la comunidad, organizado por categorías. Entra en una categoría para leer, debatir o publicar.'
            : (CommunityForumCategory::get(CommunityForumCategory::CANTINA)['description']
                ?? 'El rincón informal de Squad Alpha: quedadas, rol, videojuegos, cine y cualquier tema off-topic.');
    }
}
