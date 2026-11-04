<?php

namespace App\Http\Controllers;

use App\Services\QuickAccessNewsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class QuickAccessNewsController extends Controller
{
    public function __invoke(Request $request, QuickAccessNewsService $news): JsonResponse
    {
        abort_unless(QuickAccessNewsService::canSeeAny($request->user()), 403);

        return response()->json($news->counts($request->user()))
            ->header('Cache-Control', 'private, no-cache, no-store, must-revalidate');
    }
}
