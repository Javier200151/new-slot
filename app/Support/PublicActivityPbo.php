<?php

namespace App\Support;

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

/** Public PBO metadata only: filenames and booleans, never private disk paths or credentials. */
final class PublicActivityPbo
{
    /** @return array<string, bool|string|null> */
    public static function forActivity(Activity $activity): array
    {
        $path = $activity->pbo_path;
        $validPath = is_string($path) && ActivityPbo::isOwnStoredPath($path);
        $pboName = $validPath ? basename($path) : null;
        $htmlName = $pboName !== null ? ActivityAddonsHtml::htmlFilename($pboName) : null;

        // A database filename alone is not proof that the private file still exists.
        $pboUploaded = $validPath && Storage::disk('local')->exists($path);
        $htmlGenerated = $pboUploaded && Storage::disk('local')->exists(dirname($path) . '/' . $htmlName);

        // The export row represents the last successfully verified transfer of the PAIR.
        // Do not probe the Windows share on each public API request: it may be offline,
        // slow, or temporarily unavailable. Pending, failed and replaced exports are false.
        $export = $activity->pboExport;
        $synced = $pboUploaded
            && $htmlGenerated
            && $export !== null
            && $export->status === 'synced'
            && $export->filename === $pboName
            && $export->source_path === $path
            && filled($export->pbo_sha256)
            && filled($export->html_sha256);

        return [
            'pbo_nombre' => $pboName,
            'pbo_subido' => (bool) $pboUploaded,
            'pbo_sincronizado_windows' => (bool) $synced,
            'html_nombre' => $htmlName,
            'html_generado' => (bool) $htmlGenerated,
            'html_sincronizado_windows' => (bool) $synced,
        ];
    }
}
