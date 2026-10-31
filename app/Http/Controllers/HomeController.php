<?php

namespace App\Http\Controllers;

use App\Models\HomepageNews;
use App\Models\HomepageSetting;
use App\Services\HomepageGooglePhotosService;
use App\Services\HomepageInstagramService;
use App\Services\HomepageVodService;
use App\Services\ProfileCompletionService;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function announcements(): View
    {
        $settings = HomepageSetting::current();
        abort_unless($settings->canViewNews(auth()->user()), 403);
        $news = HomepageNews::query()
            ->where('is_published', true)
            ->where(fn ($q) => $q->whereNull('published_at')->orWhere('published_at', '<=', now()))
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->get();

        return view('news.index', compact('news', 'settings'));
    }

    public function index(
        HomepageVodService $vodService,
        HomepageInstagramService $instagramService,
        HomepageGooglePhotosService $googlePhotosService,
        ProfileCompletionService $profileCompletionService,
    ): View
    {
        $settings = HomepageSetting::current();
        $canViewNews = $settings->canViewNews(auth()->user());

        $news = HomepageNews::query()
            ->where('is_published', true)
            ->where(function ($query): void {
                $query->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            })
            ->orderByRaw('COALESCE(published_at, created_at) DESC')
            ->orderBy('sort_order')
            ->orderByDesc('id')
            ->limit($settings->newsHomeLimit())
            ->when(! $canViewNews, fn ($query) => $query->whereRaw('1 = 0'))
            ->get();

        $vods = $vodService->latest(6);
        $instagramPosts = $instagramService->latest(3);
        $googlePhotosAlbumUrl = trim((string) ($settings->google_photos_url ?: config('services.google_photos.album_url')));
        $googlePhotos = $googlePhotosService->latest(6, $googlePhotosAlbumUrl);
        $profileCompletion = auth()->check()
            ? $profileCompletionService->forUser(auth()->user())
            : null;

        return view('home', compact(
            'settings',
            'canViewNews',
            'news',
            'vods',
            'instagramPosts',
            'googlePhotos',
            'googlePhotosAlbumUrl',
            'profileCompletion',
        ));
    }
}
