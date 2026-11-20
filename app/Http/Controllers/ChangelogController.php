<?php

namespace App\Http\Controllers;

use App\Models\ChangelogEntry;
use App\Support\ChangelogAccess;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChangelogController extends Controller
{
    public function show(Request $request, ChangelogEntry $changelog): View
    {
        abort_unless(ChangelogAccess::canView($request->user()), 403);

        $entries = ChangelogEntry::query()
            ->whereKey($changelog->getKey())
            ->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')->orWhere('published_at', '<=', now());
            })
            ->paginate(12);

        abort_if($entries->isEmpty(), 404);

        return view('community.changelog.index', compact('entries'));
    }

    public function index(Request $request): View
    {
        abort_unless(ChangelogAccess::canView($request->user()), 403);

        $entries = ChangelogEntry::query()
            ->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderByDesc('release_date')
            ->orderByDesc('id')
            ->paginate(12);

        return view('community.changelog.index', compact('entries'));
    }
}
