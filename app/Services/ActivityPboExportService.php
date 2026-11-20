<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Support\ActivityAddonsHtml;
use App\Support\ActivityPbo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Synchronises saved PBOs and their Arma 3 Launcher HTML to Windows.
 * Writes ONLY to an explicitly verified CIFS mount; never to the VM fallback directory.
 * Unowned or manually modified Windows files are never overwritten.
 */
final class ActivityPboExportService
{
    public function queue(Activity $activity): void
    {
        $existing = ActivityPboExport::query()->where('activity_id', $activity->id)->first();
        if (! $activity->pbo_path && ! $existing) {
            return;
        }

        ActivityPboExport::query()->updateOrCreate(
            ['activity_id' => $activity->id],
            ['status' => 'pending', 'last_error' => null],
        );
    }

    /** Generate the HTML locally now (and schedule a transfer); no Windows connection needed. */
    public function regenerate(Activity $activity): string
    {
        if (! is_string($activity->pbo_path) || ! ActivityPbo::isOwnStoredPath($activity->pbo_path)
            || ! Storage::disk('local')->exists($activity->pbo_path)) {
            throw new RuntimeException('Guarda primero un PBO válido en la actividad.');
        }

        $htmlPath = dirname($activity->pbo_path) . '/' . ActivityAddonsHtml::htmlFilename(basename($activity->pbo_path));
        if (! Storage::disk('local')->put($htmlPath, ActivityAddonsHtml::forActivity($activity))) {
            throw new RuntimeException('No se ha podido generar el HTML de addons.');
        }
        $this->queue($activity);

        return $htmlPath;
    }

    public function sync(ActivityPboExport $export): bool
    {
        try {
            $activity = Activity::query()->find($export->activity_id);
            $directory = $this->verifiedDestination();

            // If a saved PBO is removed, only clean up files previously owned by us.
            if (! $activity || ! $activity->pbo_path) {
                $this->removeOwnedPair($directory, $export);
                $export->delete();
                return true;
            }

            $path = $activity->pbo_path;
            if (! ActivityPbo::isOwnStoredPath($path) || ! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('El PBO guardado en Laravel no se encuentra.');
            }

            $filename = basename($path);
            $htmlFilename = ActivityAddonsHtml::htmlFilename($filename);

            // Windows filesystem is case insensitive. Never let two activities share a basename.
            $duplicate = Activity::query()
                ->whereKeyNot($activity->id)
                ->whereNotNull('pbo_path')
                ->whereRaw('LOWER(pbo) = ?', [strtolower($filename)])
                ->exists();
            if ($duplicate || ActivityPboExport::query()
                ->where('activity_id', '!=', $activity->id)
                ->whereRaw('LOWER(filename) = ?', [strtolower($filename)])
                ->exists()) {
                throw new RuntimeException('Ya existe otra actividad con el mismo nombre de PBO. Cambia el nombre para evitar sobrescrituras.');
            }

            $htmlLocal = $this->regenerateLocalHtml($activity);
            $pboLocal = Storage::disk('local')->path($path);
            $pboHash = hash_file('sha256', $pboLocal);
            $htmlHash = hash_file('sha256', $htmlLocal);
            if (! $pboHash || ! $htmlHash) {
                throw new RuntimeException('No se han podido comprobar los archivos originales.');
            }

            $pboRemote = $directory . '/' . $filename;
            $htmlRemote = $directory . '/' . $htmlFilename;

            $this->verifyOwnership($pboRemote, $filename === $export->filename ? $export->pbo_sha256 : null, $pboHash);
            $this->verifyOwnership($htmlRemote, $filename === $export->filename ? $export->html_sha256 : null, $htmlHash);

            // Do not transfer hundreds of MB again when only the HTML changed.
            $needsPbo = ! is_file($pboRemote) || hash_file('sha256', $pboRemote) !== $pboHash;
            $needsHtml = ! is_file($htmlRemote) || hash_file('sha256', $htmlRemote) !== $htmlHash;
            $tempPbo = null;
            $tempHtml = null;
            try {
                if ($needsPbo) {
                    $tempPbo = tempnam($directory, '.sqa-pbo-');
                    if (! $tempPbo || ! copy($pboLocal, $tempPbo) || hash_file('sha256', $tempPbo) !== $pboHash) {
                        throw new RuntimeException('La copia o verificación del PBO ha fallado.');
                    }
                }
                if ($needsHtml) {
                    $tempHtml = tempnam($directory, '.sqa-html-');
                    if (! $tempHtml || ! copy($htmlLocal, $tempHtml) || hash_file('sha256', $tempHtml) !== $htmlHash) {
                        throw new RuntimeException('La copia o verificación del HTML ha fallado.');
                    }
                }

                // Publish staged files only after both have been verified.
                if ($needsPbo && ! rename($tempPbo, $pboRemote)) {
                    throw new RuntimeException('No se pudo publicar el PBO en Windows.');
                }
                if ($needsHtml && ! rename($tempHtml, $htmlRemote)) {
                    throw new RuntimeException('No se pudo publicar el HTML en Windows.');
                }
            } finally {
                if ($tempPbo && is_file($tempPbo)) { @unlink($tempPbo); }
                if ($tempHtml && is_file($tempHtml)) { @unlink($tempHtml); }
            }

            // Verify again after publishing to avoid claiming success on partial writes.
            if (hash_file('sha256', $pboRemote) !== $pboHash || hash_file('sha256', $htmlRemote) !== $htmlHash) {
                throw new RuntimeException('La verificación final de la copia en Windows ha fallado.');
            }

            if ($export->filename && strcasecmp($export->filename, $filename) !== 0) {
                $this->removeOwnedPair($directory, $export);
            }

            $changed = $export->filename !== $filename
                || $export->pbo_sha256 !== $pboHash
                || $export->html_sha256 !== $htmlHash;

            $export->forceFill([
                'filename' => $filename,
                'source_path' => $path,
                'pbo_sha256' => $pboHash,
                'html_sha256' => $htmlHash,
                'status' => 'synced',
                'last_error' => null,
                'synced_at' => now(),
            ])->save();

            if ($changed) {
                try {
                    app(AuditLogger::class)->automatic(
                        'activity_pbo_exported',
                        ['filename' => $filename, 'html' => $htmlFilename],
                        $activity,
                        'pbo-sync',
                    );
                } catch (Throwable $auditException) {
                    // Never mark a verified transfer as failed because audit logging failed.
                    Log::warning('No se pudo auditar una transferencia de PBO.', [
                        'activity_id' => $activity->id,
                        'reason' => $auditException->getMessage(),
                    ]);
                }
            }

            return true;
        } catch (Throwable $exception) {
            $export->forceFill([
                'status' => 'error',
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
            Log::warning('No se pudo sincronizar el PBO con Windows.', [
                'activity_id' => $export->activity_id,
                'reason' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function regenerateLocalHtml(Activity $activity): string
    {
        $htmlPath = dirname($activity->pbo_path) . '/' . ActivityAddonsHtml::htmlFilename(basename($activity->pbo_path));
        if (! Storage::disk('local')->put($htmlPath, ActivityAddonsHtml::forActivity($activity))) {
            throw new RuntimeException('No se ha podido guardar el HTML de addons.');
        }
        return Storage::disk('local')->path($htmlPath);
    }

    private function verifyOwnership(string $target, ?string $oldHash, string $newHash): void
    {
        if (! file_exists($target)) { return; }
        if (! is_file($target) || is_link($target)) {
            throw new RuntimeException('El destino contiene una ruta que no es un archivo normal.');
        }
        $existingHash = hash_file('sha256', $target);
        // Incoming hash handles a previous interrupted two-file publish.
        if (! $existingHash || ! in_array($existingHash, array_filter([$oldHash, $newHash]), true)) {
            throw new RuntimeException('Ya existe un archivo distinto en Windows con ese nombre. No se sobrescribe.');
        }
    }

    private function removeOwnedPair(string $directory, ActivityPboExport $export): void
    {
        if (! $export->filename) { return; }
        foreach ([
            $export->filename => $export->pbo_sha256,
            ActivityAddonsHtml::htmlFilename($export->filename) => $export->html_sha256,
        ] as $name => $checksum) {
            if (! $checksum) { continue; }
            $target = $directory . '/' . $name;
            if (is_file($target) && ! is_link($target) && hash_file('sha256', $target) === $checksum) {
                if (! @unlink($target)) {
                    throw new RuntimeException('No se pudo eliminar una exportación anterior de Windows.');
                }
            }
        }
    }

    private function verifiedDestination(): string
    {
        if (! config('pbo_sync.enabled')) {
            throw new RuntimeException('La sincronización de PBO todavía no está activada.');
        }
        $directory = rtrim((string) config('pbo_sync.destination'), '/');
        $expectedSource = (string) config('pbo_sync.expected_share');
        if ($directory === '' || $directory[0] !== '/' || ! is_dir($directory)) {
            throw new RuntimeException('No existe el punto de montaje configurado.');
        }

        // Do not trust is_dir alone: without SMB, this may be an ordinary Linux folder.
        $mountInfo = @file_get_contents('/proc/self/mountinfo') ?: '';
        $isCifs = false;
        foreach (explode("\n", $mountInfo) as $line) {
            if (! str_contains($line, ' - ')) { continue; }
            [$mount, $device] = explode(' - ', $line, 2);
            $fields = preg_split('/\s+/', trim($mount));
            $data = preg_split('/\s+/', trim($device));
            if (($fields[4] ?? null) !== $directory || ($data[0] ?? '') !== 'cifs') { continue; }
            if ($expectedSource !== '' && strcasecmp($data[1] ?? '', $expectedSource) !== 0) { continue; }
            $isCifs = true;
            break;
        }
        if (! $isCifs || ! is_writable($directory)) {
            throw new RuntimeException('El recurso SMB de Windows no está montado o no permite escribir.');
        }
        return $directory;
    }
}
