<?php

namespace App\Http\Controllers;

use App\Models\CommunityClip;
use App\Models\User;
use App\Models\Event;
use Illuminate\Http\JsonResponse;
use App\Services\CommunityClipService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CommunityClipController extends Controller
{
    public function index(): View
    {
        $clips = CommunityClip::visible()->with(['user','event','protagonist','protagonists'])->withCount(['likes','comments'])->orderByDesc('clip_created_at')->orderByDesc('id')->paginate(36);
        return view('clips.index', compact('clips'));
    }

    public function show(CommunityClip $clip): View
    {
        abort_unless($clip->is_visible && ! $clip->trashed(), 404);
        $clips = CommunityClip::visible()->whereKey($clip->id)
            ->with(['user', 'event', 'protagonist', 'protagonists'])->withCount(['likes', 'comments'])->paginate(36);
        $autoOpenClipId = $clip->id;
        return view('clips.index', compact('clips', 'autoOpenClipId'));
    }

    public function lookup(Request $request): JsonResponse
    {
        $contest = \App\Models\ClipYearContest::current();
        abort_unless($request->user()?->status?->name === 'ACTIVO'
            || $request->user()?->hasRole('admin')
            || ($contest && $contest->phase() === 'nominations' && $contest->allowsUser($request->user())), 403);
        $kind = $request->query('kind');
        abort_unless(in_array($kind, ['events', 'users'], true), 404);
        $search = trim((string) $request->query('q', ''));
        if (mb_strlen($search) < 2) return response()->json([]);
        if ($kind === 'users') {
            $results = User::query()->where('nick', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orderBy('nick')->limit(20)->get(['id', 'nick'])
                ->map(fn ($user) => ['id' => $user->id, 'label' => $user->nick]);
        } else {
            $results = Event::query()->where('name', 'like', '%' . addcslashes($search, '%_\\') . '%')
                ->orderByDesc('date')->limit(20)->get(['id', 'name', 'date'])
                ->map(fn ($event) => ['id' => $event->id, 'label' => $event->name . ' · ' . $event->date?->format('d/m/Y')]);
        }
        return response()->json($results);
    }

    public function store(Request $request, CommunityClipService $service): RedirectResponse
    {
        abort_unless($request->user()?->status?->name === 'ACTIVO' || $request->user()?->hasRole('admin'), 403);
        $data = $request->validate([
            'url' => ['required', 'url', 'max:600'],
            'title' => ['nullable', 'string', 'max:255'],
            'event_id' => ['nullable', 'integer', 'exists:events,id,deleted_at,NULL'],
            'protagonist_user_ids' => ['nullable', 'string', 'max:255'],
        ]);
        $service->addManual($request->user(), $data['url'], $data['title'] ?? null, $data['event_id'] ?? null, $service->parseProtagonistIds($data['protagonist_user_ids'] ?? null));
        return back()->with('success', 'Clip añadido a Squad ALPHA.');
    }

    public function destroy(Request $request, CommunityClip $clip): RedirectResponse
    {
        abort_unless((int) $clip->user_id === (int) $request->user()->id || $request->user()->can('community-clips.delete') || $request->user()->hasRole('admin'), 403);
        $clip->delete();
        return back()->with('success', 'Clip retirado de la comunidad.');
    }
}
