<?php

namespace App\Http\Controllers;

use App\Models\CommunityDiary;
use App\Models\CommunityDiaryComment;
use App\Models\CommunityDiaryEntry;
use App\Models\Event;
use App\Models\EventSlot;
use App\Models\EventSlotHistory;
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

        $diary->load([
            'author.status',
            'author.mainSqaGroup',
            'entries' => fn ($entries) => $entries
                ->latest('created_at')
                ->latest('id'),
            'entries.event.operation.operationType',
            'entries.event.eventStatus',
            'entries.comments.author.status',
            'entries.comments.author.mainSqaGroup',
            'comments.author.status',
            'comments.author.mainSqaGroup',
        ]);

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
        $missingEvents = collect();

        if ($isOwner) {
            $eventIds = $this->participatedEventIds($request->user()->id);
            $existingEventIds = $diary->entries->pluck('event_id')->filter();

            $missingEvents = Event::query()
                ->with(['operation.operationType', 'eventStatus'])
                ->whereIn('id', $eventIds)
                ->whereNotIn('id', $existingEventIds)
                ->latest('date')
                ->get();
        }

        $isSubscribed = $diary->subscriptions()
            ->where('user_id', $request->user()->id)
            ->exists();

        return view('community.diary.show', [
            'diary' => $diary,
            'isOwner' => $isOwner,
            'missingEvents' => $missingEvents,
            'isSubscribed' => $isSubscribed,
            'teamColors' => self::TEAM_COLORS,
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

        return response()->json([
            'event_id' => $event->id,
            'event_name' => $event->name,
            'group' => $squad['group'],
            'members' => $squad['members'],
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
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'content' => ['required', 'string', 'min:10', 'max:30000'],
            'squad_roster' => ['nullable', 'string', 'max:30000'],
        ]);

        $eventId = (int) $validated['event_id'];
        abort_unless(
            $this->participatedEventIds($request->user()->id)->contains($eventId),
            403,
            'Solo puedes escribir sobre eventos en los que hayas participado.'
        );

        $event = Event::query()->findOrFail($eventId);
        $squad = $this->squadMembersForEvent($request->user()->id, $event);
        $squadRoster = $this->validatedSquadRoster(
            $squad,
            $validated['squad_roster'] ?? null,
        );

        CommunityDiaryEntry::updateOrCreate(
            [
                'community_diary_id' => $diary->id,
                'user_id' => $request->user()->id,
                'event_id' => $eventId,
            ],
            [
                'content' => $validated['content'],
                'squad_group' => $squad['group'],
                'squad_roster' => $squadRoster,
            ],
        );

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
            'content' => ['required', 'string', 'min:10', 'max:30000'],
            'squad_roster' => ['nullable', 'string', 'max:30000'],
        ]);

        $event = Event::query()->findOrFail($entry->event_id);
        $payload = ['content' => $validated['content']];

        if ($request->has('squad_roster')) {
            $squad = $this->squadMembersForEvent($request->user()->id, $event);
            $payload['squad_group'] = $squad['group'];
            $payload['squad_roster'] = $this->validatedSquadRoster(
                $squad,
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
        $event->loadMissing('slots.user.status', 'slots.user.mainSqaGroup', 'slots.slotType');

        $ownCurrentSlot = $event->slots
            ->first(fn (EventSlot $slot): bool => (int) $slot->user_id === $userId);

        $ownHistory = EventSlotHistory::query()
            ->with(['user.status', 'user.mainSqaGroup', 'fromSlotType', 'toSlotType'])
            ->where('event_id', $event->id)
            ->where('user_id', $userId)
            ->latest('created_at')
            ->get();

        $group = trim((string) ($ownCurrentSlot?->slot_group ?? ''));
        if ($group === '') {
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

        $orbatOrder = collect($event->orbat['groups'] ?? [])
            ->filter(fn (array $item): bool => trim((string) ($item['name'] ?? '')) === $group)
            ->flatMap(fn (array $item): array => $item['slots'] ?? [])
            ->values()
            ->mapWithKeys(fn (array $slot, int $index): array => [
                (string) ($slot['slot_key'] ?? '') => $index,
            ]);

        $members = collect();

        foreach ($event->slots->where('slot_group', $group) as $slot) {
            if (! $slot->user_id || ! $slot->user) {
                continue;
            }

            $members->put((int) $slot->user_id, $this->squadMemberPayload(
                $slot->user,
                $slot->name,
                $slot->slotType?->name,
                $slot->slot_key,
                (int) ($orbatOrder[(string) $slot->slot_key] ?? 9999),
                $userId,
            ));
        }

        $history = EventSlotHistory::query()
            ->with(['user.status', 'user.mainSqaGroup', 'fromSlotType', 'toSlotType'])
            ->where('event_id', $event->id)
            ->whereNotNull('user_id')
            ->where(function ($query) use ($group): void {
                $query->where('to_slot_group', $group)
                    ->orWhere('from_slot_group', $group);
            })
            ->latest('created_at')
            ->get();

        foreach ($history as $movement) {
            $memberId = (int) $movement->user_id;
            if ($memberId < 1 || $members->has($memberId) || ! $movement->user) {
                continue;
            }

            $usesTo = trim((string) $movement->to_slot_group) === $group;
            $slotName = $usesTo ? $movement->to_slot_name : $movement->from_slot_name;
            $slotTypeName = $usesTo ? $movement->toSlotType?->name : $movement->fromSlotType?->name;
            $slotKey = $usesTo ? $movement->to_slot_key : $movement->from_slot_key;

            $members->put($memberId, $this->squadMemberPayload(
                $movement->user,
                $slotName,
                $slotTypeName,
                $slotKey,
                (int) ($orbatOrder[(string) $slotKey] ?? 9999),
                $userId,
            ));
        }

        return [
            'group' => $group,
            'members' => $members
                ->sortBy(fn (array $member): string => sprintf('%05d-%s', $member['orbat_order'], mb_strtolower($member['nick'])))
                ->values()
                ->map(function (array $member): array {
                    unset($member['orbat_order']);
                    return $member;
                })
                ->all(),
        ];
    }

    private function squadMemberPayload(
        $user,
        ?string $slotName,
        ?string $slotTypeName,
        ?string $slotKey,
        int $orbatOrder,
        int $ownerId,
    ): array {
        return [
            'user_id' => (int) $user->id,
            'nick' => (string) $user->nick,
            'slot_name' => trim((string) $slotName),
            'slot_type' => trim((string) $slotTypeName),
            'slot_key' => (string) $slotKey,
            'avatar' => filled($user->image)
                ? asset('storage/' . ltrim((string) $user->image, '/'))
                : asset('images/sqa-shield-white.png'),
            'profile_color' => $user->getFrontendColor(),
            'is_owner' => (int) $user->id === $ownerId,
            'orbat_order' => $orbatOrder,
        ];
    }

    private function validatedSquadRoster(array $squad, ?string $json): array
    {
        $allowed = collect($squad['members'] ?? [])->keyBy('user_id');

        if ($allowed->isEmpty()) {
            return [];
        }

        $submitted = blank($json) ? [] : json_decode($json, true);
        if (! is_array($submitted)) {
            throw ValidationException::withMessages([
                'squad_roster' => 'La numeración de escuadra no tiene un formato válido.',
            ]);
        }

        $colors = array_keys(self::TEAM_COLORS);
        $normalized = [];
        $seen = [];

        foreach (array_slice($submitted, 0, 50) as $row) {
            $memberId = (int) ($row['user_id'] ?? 0);
            if ($memberId < 1 || isset($seen[$memberId]) || ! $allowed->has($memberId)) {
                continue;
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

            $source = $allowed->get($memberId);
            $normalized[] = [
                'user_id' => $memberId,
                'nick' => $source['nick'],
                'slot_name' => $source['slot_name'],
                'slot_type' => $source['slot_type'],
                'number' => $number === '' ? null : (int) $number,
                'color' => $color === '' ? null : $color,
            ];
            $seen[$memberId] = true;
        }

        // El snapshot siempre conserva a todos los compañeros detectados en la
        // escuadra. Si el usuario no los ordenó manualmente, se añaden al final
        // siguiendo el orden del ORBAT y sin número/color asignados.
        foreach ($allowed as $memberId => $source) {
            if (isset($seen[(int) $memberId])) {
                continue;
            }

            $normalized[] = [
                'user_id' => (int) $memberId,
                'nick' => $source['nick'],
                'slot_name' => $source['slot_name'],
                'slot_type' => $source['slot_type'],
                'number' => null,
                'color' => null,
            ];
        }

        return $normalized;
    }

}
