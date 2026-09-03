<?php

namespace App\Http\Controllers;

use App\Models\HomepageNews;
use App\Models\HomepageSetting;
use App\Services\HomepageInstagramService;
use App\Services\HomepageVodService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function index(HomepageVodService $vodService, HomepageInstagramService $instagramService): View
    {
        $settings = HomepageSetting::current();

        $news = HomepageNews::query()
            ->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit(6)
            ->get();

        $vods = $vodService->latest(6);
        $instagramPosts = $instagramService->latest(3);

        return view('home', compact('settings', 'news', 'vods', 'instagramPosts'));
    }
}
