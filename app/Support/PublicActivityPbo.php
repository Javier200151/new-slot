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

        // HTML is no longer generated or transferred. It cannot block a verified PBO.
        // The public legacy HTML fields are kept for existing integrations.
        $export = $activity->pboExport;
        $synced = $pboUploaded
            && $export !== null
            && $export->status === 'synced'
            && $export->filename === $pboName
            && $export->source_path === $path
            && filled($export->pbo_sha256);
        $htmlSynced = $synced && $htmlGenerated && filled($export?->html_sha256);

        return [
            'pbo_nombre' => $pboName,
            'pbo_subido' => (bool) $pboUploaded,
            'pbo_sincronizado_windows' => (bool) $synced,
            'html_nombre' => $htmlName,
            'html_generado' => (bool) $htmlGenerated,
            'html_sincronizado_windows' => (bool) $htmlSynced,
        ];
    }
}
