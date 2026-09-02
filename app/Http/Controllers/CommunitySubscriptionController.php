<?php

namespace App\Http\Controllers;

use App\Models\CommunityDiary;
use App\Models\CommunityPost;
use App\Support\CommunityArea;
use App\Support\CommunityForumCategory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommunitySubscriptionController extends Controller
{
    public function toggle(Request $request, string $type, int $id): RedirectResponse
    {
        $subject = match ($type) {
            'hilo' => CommunityPost::query()->findOrFail($id),
            'diario' => CommunityDiary::query()->findOrFail($id),
            default => abort(404),
        };

        if ($subject instanceof CommunityPost) {
            $section = $subject->channel === 'personal'
                ? CommunityArea::FORUM
                : CommunityArea::CANTINA;

            abort_unless(CommunityArea::can($request->user(), $section), 403);

            $categoryKey = CommunityForumCategory::keyForPost($subject);
            abort_unless(CommunityForumCategory::canView($request->user(), $categoryKey), 403);
        } else {
            abort_unless(CommunityArea::can($request->user(), CommunityArea::DIARY), 403);
        }

        $existing = $subject->subscriptions()
            ->where('user_id', $request->user()->id)
            ->first();

        if ($existing) {
            $existing->delete();

            return back()->with('status', 'subscription-disabled');
        }

        $subject->subscriptions()->create([
            'user_id' => $request->user()->id,
        ]);

        return back()->with('status', 'subscription-enabled');
    }
}
