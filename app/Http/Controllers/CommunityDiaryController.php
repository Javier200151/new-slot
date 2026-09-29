<?php

namespace App\Http\Controllers;

use App\Models\CommunityDiary;
use App\Models\CommunityDiaryComment;
use App\Models\CommunityDiaryEntry;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
use App\Models\User;
use App\Services\CommunitySubscriptionService;
use App\Support\CommunityArea;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class CommunityDiaryController extends Controller
{
    private const TEAM_COLORS = [
        'red' => 'Rojo',
        'blue' => 'Azul',
        'green' => 'Verde',
        'yellow' => 'Amarillo',
        'white' => 'Blanco',
        'orange' => 'Naranja',
        'purple' => 'Morado',
        'pink' => 'Rosa',
        'cyan' => 'Cian',
    ];

    public function index(Request $request): View
    {
        $this->authorizeDiary($request);

        $diaries = CommunityDiary::query()
            ->with([
                'author.status',
                'author.mainSqaGroup',
            ])
            ->withCount(['entries', 'comments'])
            ->withExists([
                'subscriptions as is_subscribed' => fn ($subscriptions) =>
                    $subscriptions->where('user_id', $request->user()->id),
            ])
            ->latest('updated_at')
            ->paginate(18);

        $myDiary = CommunityDiary::query()
            ->where('user_id', $request->user()->id)
            ->first();

        return view('community.diary.index', [
            'diaries' => $diaries,
            'myDiary' => $myDiary,
            'canStartDiary' => ! $myDiary && $this->canStartDiary($request),
        ]);
    }

    public function start(Request $request): RedirectResponse
    {
        $this->authorizeDiary($request);
        abort_unless(
            $this->canStartDiary($request),
            403,
            'El diario puede iniciarse siendo recluta o miembro.'
        );

        $diary = CommunityDiary::firstOrCreate(
            ['user_id' => $request->user()->id],
            ['author_nick' => $request->user()->nick],
        );

        $diary->subscriptions()->firstOrCreate([
            'user_id' => $request->user()->id,
        ]);

        return redirect()
            ->route('community.diary.show', $diary)
            ->with('status', 'diary-started');
    }

    public function show(Request $request, CommunityDiary $diary): View
    {
        $this->authorizeDiary($request);

        $entryOrder = $request->query('orden') === 'antiguos'
            ? 'antiguos'
            : 'nuevos';

        $diary->load([
            'author.status',
            'author.mainSqaGroup',
            'entries' => fn ($entries) => $entryOrder === 'antiguos'
                ? $entries->oldest('created_at')->oldest('id')
                : $entries->latest('created_at')->latest('id'),
            'entries.event.activity.activityType',
            'entries.event.eventStatus',
            'entries.comments.author.status',
            'entries.comments.author.mainSqaGroup',
            'comments.author.status',
            'comments.author.mainSqaGroup',
        ]);

        $diary->markReadBy($request->user());

        $authors = collect([$diary->author])
            ->merge($diary->entries->flatMap(
                fn (CommunityDiaryEntry $entry) => $entry->comments->pluck('author')
            ))
            ->merge($diary->comments->pluck('author'))
            ->filter()
            ->unique('id')
            ->values();
        $this->hydrateAuthorActivity($authors);

        $isOwner = $diary->user_id === $request->user()->id;
        $availableEvents = collect();
        $allUsers = collect();

        if ($isOwner) {
            $eventIds = $this->participatedEventIds($request->user()->id);
            $existingEventIds = $diary->entries->pluck('event_id')->filter();

            $availableEvents = Event::query()
                ->with(['activity.activityType', 'eventStatus'])
                ->whereIn('id', $eventIds)
                ->whereNotIn('id', $existingEventIds)
                ->latest('date')
                ->get();

            $allUsers = User::query()
                ->with(['status', 'mainSqaGroup'])
                ->orderBy('nick')
                ->get()
                ->map(fn (User $user): array => [
                    'user_id' => (int) $user->id,
                    'nick' => (string) $user->nick,
                    'avatar' => filled($user->image)
                        ? asset('storage/' . ltrim((string) $user->image, '/'))
                        : asset('images/sqa-shield-white.png'),
                    'profile_color' => $user->getFrontendColor(),
                ])
                ->values();
        }

        $isSubscribed = $diary->subscriptions()
            ->where('user_id', $request->user()->id)
            ->exists();

        return view('community.diary.show', [
            'diary' => $diary,
            'isOwner' => $isOwner,
            'availableEvents' => $availableEvents,
            'allUsers' => $allUsers,
            'isSubscribed' => $isSubscribed,
            'teamColors' => self::TEAM_COLORS,
            'entryOrder' => $entryOrder,
        ]);
    }

    public function eventSquad(Request $request, Event $event): JsonResponse
    {
        $this->authorizeDiary($request);

        abort_unless(
            $this->participatedEventIds($request->user()->id)->contains((int) $event->id),
            403,
            'Solo puedes consultar la escuadra de eventos en los que hayas participado.'
        );

        $squad = $this->squadMembersForEvent($request->user()->id, $event);
        $participants = $this->eventParticipantPool($event, $request->user()->id)
            ->values()
            ->all();

        return response()->json([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'group' => $squad['group'],
            'members' => $squad['members'],
            'available_members' => $participants,
            'colors' => self::TEAM_COLORS,
        ]);
    }

    public function store(Request $request, CommunitySubscriptionService $subscriptions): RedirectResponse
    {
        $this->authorizeDiary($request);

        $diary = CommunityDiary::query()
            ->where('user_id', $request->user()->id)
            ->firstOrFail();

        $validated = $request->validate([
            'event_id' => ['nullable', 'integer', 'exists:events,id'],
            'entry_title' => ['nullable', 'string', 'min:2', 'max:255'],
            'content' => ['required', 'string', 'min:10', 'max:30000'],
            'squad_group' => ['nullable', 'string', 'max:255'],
            'squad_roster' => ['nullable', 'string', 'max:30000'],
        ]);

        $event = null;
        $eventId = filled($validated['event_id'] ?? null)
            ? (int) $validated['event_id']
            : null;

        $detectedGroup = null;

        if ($eventId) {
            abort_unless(
                $this->participatedEventIds($request->user()->id)->contains($eventId),
                403,
                'Solo puedes escribir sobre eventos en los que hayas participado.'
            );

            $event = Event::query()->findOrFail($eventId);
            $detectedGroup = $this->squadMembersForEvent($request->user()->id, $event)['group'];
        }

        if (! $event && blank($validated['entry_title'] ?? null)) {
            throw ValidationException::withMessages([
                'entry_title' => 'Indica un título para la actividad realizada.',
            ]);
        }

        $squadRoster = $this->validatedSquadRoster(
            $event,
            $request->user()->id,
            $validated['squad_roster'] ?? null,
        );

        $entryPayload = [
            'entry_title' => $event
                ? (string) $event->name
                : trim((string) ($validated['entry_title'] ?? '')),
            'content' => $validated['content'],
            'squad_group' => $event
                ? $detectedGroup
                : (filled($validated['squad_group'] ?? null)
                    ? trim((string) $validated['squad_group'])
                    : null),
            'squad_roster' => $squadRoster,
        ];

        if ($eventId) {
            CommunityDiaryEntry::updateOrCreate(
                [
                    'community_diary_id' => $diary->id,
                    'user_id' => $request->user()->id,
                    'event_id' => $eventId,
                ],
                $entryPayload,
            );
        } else {
            CommunityDiaryEntry::create([
                'community_diary_id' => $diary->id,
                'user_id' => $request->user()->id,
                'event_id' => null,
                ...$entryPayload,
            ]);
        }

        $diary->touch();
        $subscriptions->notifyDiary($diary, $request->user(), 'new_entry');

        return redirect()
            ->route('community.diary.show', $diary)
            ->with('status', 'diary-saved');
    }

    public function update(
        Request $request,
        CommunityDiaryEntry $entry,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeDiary($request);
        abort_unless($entry->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'entry_title' => ['nullable', 'string', 'min:2', 'max:255'],
            'content' => ['required', 'string', 'min:10', 'max:30000'],
            'squad_group' => ['nullable', 'string', 'max:255'],
            'squad_roster' => ['nullable', 'string', 'max:30000'],
        ]);

        $event = $entry->event_id
            ? Event::query()->find($entry->event_id)
            : null;

        if (! $event && blank($validated['entry_title'] ?? null)) {
            throw ValidationException::withMessages([
                'entry_title' => 'Indica un título para la actividad realizada.',
            ]);
        }

        $detectedGroup = $event
            ? $this->squadMembersForEvent($request->user()->id, $event)['group']
            : null;

        $payload = [
            'entry_title' => $event
                ? (string) $event->name
                : trim((string) ($validated['entry_title'] ?? '')),
            'content' => $validated['content'],
            'squad_group' => $event
                ? $detectedGroup
                : (filled($validated['squad_group'] ?? null)
                    ? trim((string) $validated['squad_group'])
                    : null),
        ];

        if ($request->has('squad_roster')) {
            $payload['squad_roster'] = $this->validatedSquadRoster(
                $event,
                $request->user()->id,
                $validated['squad_roster'] ?? null,
            );
        }

        $entry->update($payload);
        $subscriptions->notifyDiary($entry->diary, $request->user(), 'entry_updated');

        return back()->with('status', 'diary-saved');
    }

    public function destroy(Request $request, CommunityDiaryEntry $entry): RedirectResponse
    {
        $this->authorizeDiary($request);
        abort_unless($entry->user_id === $request->user()->id, 403);

        $diary = $entry->diary;
        $entry->delete();
        $diary?->touch();

        return back()->with('status', 'diary-deleted');
    }

    public function comment(
        Request $request,
        CommunityDiary $diary,
        CommunityDiaryEntry $entry,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeDiary($request);
        abort_unless((int) $entry->community_diary_id === (int) $diary->id, 404);

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:20000'],
        ]);

        CommunityDiaryComment::create([
            'community_diary_id' => $diary->id,
            'community_diary_entry_id' => $entry->id,
            'user_id' => $request->user()->id,
            'body' => $validated['body'],
        ]);

        $diary->touch();
        $subscriptions->notifyDiary($diary, $request->user(), 'new_reply');

        return redirect()
            ->to(route('community.diary.show', $diary) . '#entrada-' . $entry->id)
            ->with('status', 'comment-created');
    }

    public function updateComment(
        Request $request,
        CommunityDiary $diary,
        CommunityDiaryComment $comment,
        CommunitySubscriptionService $subscriptions,
    ): RedirectResponse {
        $this->authorizeDiary($request);
        abort_unless($comment->community_diary_id === $diary->id, 404);
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->hasRole('admin'),
            403
        );

        $validated = $request->validate([
            'body' => ['required', 'string', 'min:2', 'max:20000'],
        ]);

        $comment->update(['body' => $validated['body']]);
        $diary->touch();
        $subscriptions->notifyDiary($diary, $request->user(), 'reply_updated');

        return redirect()
            ->to(
                route('community.diary.show', $diary)
                . '#entrada-'
                . ($comment->community_diary_entry_id ?: '')
            )
            ->with('status', 'comment-updated');
    }

    public function destroyComment(
        Request $request,
        CommunityDiary $diary,
        CommunityDiaryComment $comment,
    ): RedirectResponse {
        $this->authorizeDiary($request);
        abort_unless($comment->community_diary_id === $diary->id, 404);
        abort_unless(
            $comment->user_id === $request->user()->id || $request->user()->hasRole('admin'),
            403
        );

        $comment->delete();
        $diary->touch();

        return back()->with('status', 'comment-deleted');
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

    private function authorizeDiary(Request $request): void
    {
        abort_unless(
            CommunityArea::can($request->user(), CommunityArea::DIARY),
            403,
            'No tienes acceso al diario.'
        );
    }

    private function canStartDiary(Request $request): bool
    {
        if ($request->user()->hasRole('admin')) {
            return true;
        }

        return in_array(
            CommunityArea::status($request->user()),
            ['RECLUTA', 'ACTIVO'],
            true,
        );
    }

    private function participatedEventIds(int $userId): Collection
    {
        $current = EventSlot::query()
            ->where('user_id', $userId)
            ->pluck('event_id');

        $historical = EventSlotHistory::query()
            ->where('user_id', $userId)
            ->whereNotNull('event_id')
            ->pluck('event_id');

        return $current
            ->merge($historical)
            ->filter()
            ->map(fn ($id) => (int) $id)
            ->unique()
            ->values();
    }

    private function squadMembersForEvent(int $userId, Event $event): array
    {
        $participants = $this->eventParticipantPool($event, $userId);

        $event->loadMissing('slots.user.status', 'slots.user.mainSqaGroup');

        $ownCurrentSlot = $event->slots
            ->first(fn (EventSlot $slot): bool => (int) $slot->user_id === $userId);

        $group = trim((string) ($ownCurrentSlot?->slot_group ?? ''));

        if ($group === '') {
            $ownHistory = EventSlotHistory::query()
                ->where('event_id', $event->id)
                ->where('user_id', $userId)
                ->latest('created_at')
                ->get();

            foreach ($ownHistory as $movement) {
                $candidate = trim((string) ($movement->to_slot_group ?: $movement->from_slot_group));
                if ($candidate !== '') {
                    $group = $candidate;
                    break;
                }
            }
        }

        if ($group === '') {
            return ['group' => null, 'members' => []];
        }

        $members = $participants
            ->filter(fn (array $member): bool => trim((string) ($member['slot_group'] ?? '')) === $group)
            ->values()
            ->map(function (array $member): array {
                unset($member['slot_group'], $member['orbat_order']);
                return $member;
            })
            ->all();

        return [
            'group' => $group,
            'members' => $members,
        ];
    }

    private function eventParticipantPool(Event $event, int $ownerId): Collection
    {
        $event->loadMissing('slots.user.status', 'slots.user.mainSqaGroup');

        $orbatGroups = collect($event->orbat['groups'] ?? []);
        $groupOrder = $orbatGroups
            ->values()
            ->mapWithKeys(fn (array $group, int $index): array => [
                trim((string) ($group['name'] ?? '')) => $index,
            ]);

        $slotOrder = $orbatGroups
            ->flatMap(fn (array $group): array => $group['slots'] ?? [])
            ->values()
            ->mapWithKeys(fn (array $slot, int $index): array => [
                (string) ($slot['slot_key'] ?? '') => $index,
            ]);

        $participants = collect();

        foreach ($event->slots as $slot) {
            if (! $slot->user_id || ! $slot->user) {
                continue;
            }

            $participants->put((int) $slot->user_id, $this->participantPayload(
                $slot->user,
                $slot->name,
                $slot->slot_key,
                $slot->slot_group,
                (int) ($groupOrder[trim((string) $slot->slot_group)] ?? 9999),
                (int) ($slotOrder[(string) $slot->slot_key] ?? 9999),
                $ownerId,
            ));
        }

        $history = EventSlotHistory::query()
            ->with(['user.status', 'user.mainSqaGroup'])
            ->where('event_id', $event->id)
            ->whereNotNull('user_id')
            ->latest('created_at')
            ->get();

        foreach ($history as $movement) {
            $memberId = (int) $movement->user_id;
            if ($memberId < 1 || $participants->has($memberId) || ! $movement->user) {
                continue;
            }

            $slotKey = trim((string) ($movement->to_slot_key ?: $movement->from_slot_key));
            $slotGroup = trim((string) ($movement->to_slot_group ?: $movement->from_slot_group));
            $slotName = $movement->to_slot_name ?: $movement->from_slot_name;
            $participants->put($memberId, $this->participantPayload(
                $movement->user,
                $slotName,
                $slotKey,
                $slotGroup,
                (int) ($groupOrder[$slotGroup] ?? 9999),
                (int) ($slotOrder[$slotKey] ?? 9999),
                $ownerId,
            ));
        }

        return $participants
            ->sortBy(fn (array $member): string => sprintf(
                '%05d-%05d-%s',
                (int) ($member['group_order'] ?? 9999),
                (int) ($member['orbat_order'] ?? 9999),
                mb_strtolower((string) ($member['nick'] ?? '')),
            ))
            ->values();
    }

    private function participantPayload(
        $user,
        ?string $slotName,
        ?string $slotKey,
        ?string $slotGroup,
        int $groupOrder,
        int $orbatOrder,
        int $ownerId,
    ): array {
        return [
            'user_id' => (int) $user->id,
            'nick' => (string) $user->nick,
            'slot_name' => trim((string) $slotName),
            'slot_key' => (string) $slotKey,
            'slot_group' => trim((string) $slotGroup),
            'avatar' => filled($user->image)
                ? asset('storage/' . ltrim((string) $user->image, '/'))
                : asset('images/sqa-shield-white.png'),
            'profile_color' => $user->getFrontendColor(),
            'is_owner' => (int) $user->id === $ownerId,
            'group_order' => $groupOrder,
            'orbat_order' => $orbatOrder,
        ];
    }

    private function validatedSquadRoster(?Event $event, int $userId, ?string $json): array
    {
        $submitted = blank($json) ? [] : json_decode($json, true);
        if (! is_array($submitted)) {
            throw ValidationException::withMessages([
                'squad_roster' => 'La numeración de escuadra no tiene un formato válido.',
            ]);
        }

        $rows = collect(array_slice($submitted, 0, 100));
        $memberIds = $rows
            ->pluck('user_id')
            ->map(fn ($id): int => (int) $id)
            ->filter(fn (int $id): bool => $id > 0)
            ->unique()
            ->values();

        if ($rows->isNotEmpty() && $memberIds->isEmpty()) {
            throw ValidationException::withMessages([
                'squad_roster' => 'Selecciona usuarios existentes para formar la escuadra del diario.',
            ]);
        }

        $users = User::withTrashed()
            ->whereIn('id', $memberIds)
            ->get()
            ->keyBy('id');

        if ($users->count() !== $memberIds->count()) {
            throw ValidationException::withMessages([
                'squad_roster' => 'Uno de los usuarios seleccionados ya no existe.',
            ]);
        }

        $eventParticipants = $event
            ? $this->eventParticipantPool($event, $userId)->keyBy('user_id')
            : collect();

        $colors = array_keys(self::TEAM_COLORS);
        $normalized = [];
        $seenUsers = [];

        foreach ($rows as $row) {
            $memberId = (int) ($row['user_id'] ?? 0);

            if ($memberId < 1 || isset($seenUsers[$memberId])) {
                continue;
            }

            $user = $users->get($memberId);
            if (! $user) {
                continue;
            }

            $eventSource = $eventParticipants->get($memberId);
            $slotName = trim((string) ($row['slot_name'] ?? ''));

            if ($slotName === '' && $eventSource) {
                $slotName = trim((string) ($eventSource['slot_name'] ?? ''));
            }

            if (mb_strlen($slotName) > 120) {
                throw ValidationException::withMessages([
                    'squad_roster' => 'Los nombres de slot no pueden superar 120 caracteres.',
                ]);
            }

            $number = trim((string) ($row['number'] ?? ''));
            if ($number !== '' && (! ctype_digit($number) || (int) $number < 1 || (int) $number > 99)) {
                throw ValidationException::withMessages([
                    'squad_roster' => 'La numeración debe estar entre 1 y 99. Los números pueden repetirse.',
                ]);
            }

            $color = strtolower(trim((string) ($row['color'] ?? '')));
            if ($color !== '' && ! in_array($color, $colors, true)) {
                throw ValidationException::withMessages([
                    'squad_roster' => 'Uno de los colores de equipo no es válido.',
                ]);
            }

            $normalized[] = [
                'user_id' => $memberId,
                'nick' => (string) $user->nick,
                'slot_name' => $slotName !== '' ? $slotName : null,
                'number' => $number === '' ? null : (int) $number,
                'color' => $color === '' ? null : $color,
            ];

            $seenUsers[$memberId] = true;
        }

        return $normalized;
    }

}
