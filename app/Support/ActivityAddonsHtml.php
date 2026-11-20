<?php

namespace App\Support;

use App\Models\Activity;
use App\Models\Addon;

/** Arma 3 Launcher import format, shared by the download button and PBO sync. */
final class ActivityAddonsHtml
{
    public static function forActivity(Activity $activity): string
    {
        return self::fromIds($activity->addons['addon_ids'] ?? []);
    }

    public static function fromIds(array $addonIds): string
    {
        $ids = collect($addonIds)->map(fn ($id): int => (int) $id)->filter()->unique()->all();
        $names = Addon::query()->whereIn('id', $ids)->orderBy('name')->pluck('name')->all();

        return self::fromNames($names);
    }

    public static function fromNames(array $names): string
    {
        $rows = collect($names)->map(function ($name): string {
            $safe = htmlspecialchars((string) $name, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

            return <<<HTML
  <tr data-type="ModContainer">
    <td data-type="DisplayName">{$safe}</td>
  </tr>
HTML;
        })->implode("\n");

        return <<<HTML
<html><head></head><body><table>
  <tbody>{$rows}
</tbody></table></body></html>
HTML;
    }

    public static function htmlFilename(string $pboFilename): string
    {
        return substr($pboFilename, 0, -4) . '.html';
    }
}
