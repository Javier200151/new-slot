<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\Addon;
use App\Support\ActivityAddonsHtml;
use Illuminate\Http\Response;
use Illuminate\Support\Str;

class PublicActivityAddonsHtmlController extends Controller
{
    public function __invoke(Activity $activity): Response
    {
        $activity->loadMissing(['activityType', 'platform']);
        abort_unless(
            ($activity->activityType?->usesAddons() ?? true)
                && ! ($activity->platform?->isReforger() ?? false),
            404,
        );

        $ids = collect($activity->addons['addon_ids'] ?? [])
            ->map(fn ($id): int => (int) $id)
            ->filter()
            ->unique()
            ->all();

        abort_unless($ids && Addon::query()->whereIn('id', $ids)->exists(), 404);

        $filename = Str::slug($activity->name ?: 'actividad') . '-addons.html';

        return response(ActivityAddonsHtml::fromIds($ids), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
