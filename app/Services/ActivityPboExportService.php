<?php

namespace App\Services;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Services\PersistentCampaignPbo;
use App\Jobs\SyncActivityPboExport;
use App\Support\ActivityPbo;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

/**
 * Synchronises verified PBOs to Windows; addons are fetched from the activity API.
 * Writes ONLY to an explicitly verified CIFS mount; never to the VM fallback directory.
 * Existing Windows files are overwritten only after explicit user confirmation.
 */
final class ActivityPboExportService
{
    /** Detect name collisions before the form is saved (including a replacement in the same activity). */
    public function replacementRequired(string $filename, ?int $activityId = null): bool
    {
        if (! ActivityPbo::validFilename($filename)) { return false; }

        // Even replacing a PBO in the current activity needs an explicit decision.
        if (Activity::query()->whereRaw('LOWER(pbo) = ?', [strtolower($filename)])
            ->whereNotNull('pbo_path')->exists()) { return true; }

        // The share may be offline or not mounted in PHP; in that case the final sync
        // will refuse to overwrite unknown Windows content without approval.
        try {
            $directory = $this->verifiedDestination();
            return file_exists($directory . '/' . $filename);
        } catch (Throwable) {
            return false;
        }
    }

    /** One queued export per persistent campaign; independent exports otherwise. */
    public function queue(Activity $activity, bool $replaceApproved = false, bool $htmlOverwrite = false): void
    {
        $this->queueKey((string) ($activity->pbo_key ?: PersistentCampaignPbo::key($activity)),
            $activity->id, $replaceApproved, $htmlOverwrite);
    }

    public function queueKey(string $key, ?int $htmlActivityId = null, bool $replaceApproved = false, bool $htmlOverwrite = false): void
    {
        if ($key === '') { return; }
        $exists = ActivityPboExport::query()->where('pbo_key', $key)->exists();
        $hasFiles = Activity::query()->where('pbo_key', $key)->whereNotNull('pbo_path')->exists();
        if (! $exists && ! $hasFiles) { return; }

        $values = [
            'status' => 'pending',
            'last_error' => null,
            'replacement_approved' => $replaceApproved,
            'html_overwrite_approved' => $htmlOverwrite,
        ];
        if ($htmlActivityId) { $values['html_activity_id'] = $htmlActivityId; }
        $export = ActivityPboExport::query()->updateOrCreate(['pbo_key' => $key], $values);
        if (config('pbo_sync.enabled')) {
            SyncActivityPboExport::dispatch($export->id)->afterCommit();
        }
    }

    public function sync(ActivityPboExport $export): bool
    {
        try {
            // Exports are owned by a group, not a single (possibly deleted) activity.
            $members = Activity::query()->where('pbo_key', $export->pbo_key)
                ->whereNotNull('pbo_path')->orderByDesc('pbo_uploaded_at')->orderByDesc('id')->get();
            $directory = $this->verifiedDestination();
            if ($members->isEmpty()) {
                // Another still-live activity may use this same Windows basename.
                if (! $export->filename || ! Activity::query()->where('pbo_key', '!=', $export->pbo_key)
                    ->whereRaw('LOWER(pbo) = ?', [strtolower($export->filename)])->exists()) {
                    $this->removeOwnedPbo($directory, $export);
                }
                $export->delete();
                return true;
            }
            if ($members->pluck('pbo_path')->unique()->count() > 1) {
                throw new RuntimeException('La campaña tiene PBO distintos: revisa sqa:share-pbos antes de sincronizar.');
            }
            $activity = $members->first();
            $path = $members->first()->pbo_path;
            if (! ActivityPbo::isOwnStoredPath($path) || ! Storage::disk('local')->exists($path)) {
                throw new RuntimeException('El PBO guardado en Laravel no se encuentra.');
            }
            $filename = basename($path);

            // The Windows folder is flat. Never let an unrelated persistent campaign
            // silently replace a mission belonging to a different export key.
            $collision = ActivityPboExport::query()->where('pbo_key', '!=', $export->pbo_key)
                ->whereRaw('LOWER(filename) = ?', [strtolower($filename)])->where('status', 'synced')->exists();
            if ($collision && str_starts_with((string) $export->pbo_key, 'campaign:')) {
                throw new RuntimeException('Otro PBO independiente utiliza este nombre en Windows. No se ha sobrescrito.');
            }
            // Non-persistent, independent activities keep the previous confirmation semantics.
            $duplicate = Activity::query()->where('pbo_key', '!=', $export->pbo_key)
                ->whereNotNull('pbo_path')->whereRaw('LOWER(pbo) = ?', [strtolower($filename)])->exists();
            if ($duplicate && ! $export->replacement_approved && ! str_starts_with((string) $export->pbo_key, 'campaign:')) {
                throw new RuntimeException('Ya existe otra actividad con el mismo PBO. Confirma la sustitución desde Filament.');
            }

            $pboLocal = Storage::disk('local')->path($path);
            $pboHash = hash_file('sha256', $pboLocal);
            if (! $pboHash) {
                throw new RuntimeException('No se ha podido comprobar el archivo PBO original.');
            }

            $pboRemote = $directory . '/' . $filename;
            $this->verifyOwnership($pboRemote, $filename === $export->filename ? $export->pbo_sha256 : null, $pboHash, (bool) $export->replacement_approved);
            $needsPbo = ! is_file($pboRemote) || hash_file('sha256', $pboRemote) !== $pboHash;
            $staged = [];
            $backups = [];
            $published = [];
            $publishedSuccessfully = false;
            try {
                foreach ([
                    $pboRemote => [$pboLocal, $pboHash, $needsPbo],
                ] as $destination => [$source, $hash, $needed]) {
                    if (! $needed) { continue; }
                    $temporary = tempnam($directory, '.sqa-upload-');
                    if (! $temporary || ! copy($source, $temporary) || hash_file('sha256', $temporary) !== $hash) {
                        throw new RuntimeException('No se pudo copiar o verificar un archivo en Windows.');
                    }
                    $staged[$destination] = $temporary;
                }

                // Move the previous PBO to a temporary backup before publishing the verified copy.
                // A failed publication restores the previous PBO instead of losing it.
                foreach ($staged as $destination => $temporary) {
                    if (file_exists($destination)) {
                        $backup = tempnam($directory, '.sqa-previous-');
                        if (! $backup) { throw new RuntimeException('No se pudo preparar la copia de seguridad.'); }
                        @unlink($backup);
                        if (! rename($destination, $backup)) {
                            throw new RuntimeException('No se pudo apartar el archivo anterior de Windows.');
                        }
                        $backups[$destination] = $backup;
                    }
                    if (! rename($temporary, $destination)) {
                        throw new RuntimeException('No se pudo publicar un archivo en Windows.');
                    }
                    $published[] = $destination;
                    unset($staged[$destination]);
                }

                if (hash_file('sha256', $pboRemote) !== $pboHash) {
                    throw new RuntimeException('La verificación final en Windows ha fallado.');
                }
                $publishedSuccessfully = true;
            } catch (Throwable $publicationError) {
                foreach ($published as $destination) { @unlink($destination); }
                foreach ($backups as $destination => $backup) {
                    // Only recover where we can safely restore the original.
                    if (is_file($backup) && ! file_exists($destination)) { @rename($backup, $destination); }
                }
                throw $publicationError;
            } finally {
                foreach ($staged as $temporary) { if (is_file($temporary)) { @unlink($temporary); } }
                // Preserve any backup if rollback was not completed; never discard the only copy.
                if ($publishedSuccessfully) {
                    foreach ($backups as $backup) { if (is_file($backup)) { @unlink($backup); } }
                }
            }

            // Verify again after publishing to avoid claiming success on partial writes.
            if (hash_file('sha256', $pboRemote) !== $pboHash) {
                throw new RuntimeException('La verificación final de la copia en Windows ha fallado.');
            }

            if ($export->filename && strcasecmp($export->filename, $filename) !== 0
                && ! Activity::query()->where('pbo_key', '!=', $export->pbo_key)->where('pbo', $export->filename)->exists()) {
                $this->removeOwnedPbo($directory, $export);
            }

            $changed = $export->filename !== $filename
                || $export->pbo_sha256 !== $pboHash;

            // This activity becomes the new owner of the filename in /web.
            if ($export->replacement_approved) {
                ActivityPboExport::query()->where('pbo_key', '!=', $export->pbo_key)
                    ->whereRaw('LOWER(filename) = ?', [strtolower($filename)])
                    ->update(['status' => 'replaced', 'last_error' => 'Archivo sustituido desde otra actividad.']);
            }

            $export->forceFill([
                'replacement_approved' => false,
                'html_overwrite_approved' => false,
                'filename' => $filename,
                'source_path' => $path,
                'pbo_sha256' => $pboHash,
                'status' => 'synced',
                'last_error' => null,
                'synced_at' => now(),
            ])->save();

            if ($changed) {
                try {
                    app(AuditLogger::class)->automatic(
                        'activity_pbo_exported',
                        ['filename' => $filename, 'other_activity_ids' => $members->where('id', '!=', $activity->id)->pluck('id')->all()],
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
                'pbo_key' => $export->pbo_key,
                'reason' => $exception->getMessage(),
            ]);
            return false;
        }
    }

    private function verifyOwnership(string $target, ?string $oldHash, string $newHash, bool $confirmed): void
    {
        if (! file_exists($target)) { return; }
        if (! is_file($target) || is_link($target)) {
            throw new RuntimeException('El destino contiene una ruta que no es un archivo normal.');
        }
        $existingHash = hash_file('sha256', $target);
        if (! $existingHash) { throw new RuntimeException('No se puede verificar el archivo de Windows.'); }
        if ($confirmed || in_array($existingHash, array_filter([$oldHash, $newHash]), true)) {
            return;
        }
        throw new RuntimeException('Ya existe un archivo distinto en Windows con ese nombre. Debes confirmar su sustitución desde Filament.');
    }

    /** Never delete historical HTML files; only remove a PBO with an owned hash. */
    private function removeOwnedPbo(string $directory, ActivityPboExport $export): void
    {
        if (! $export->filename) { return; }
        foreach ([$export->filename => $export->pbo_sha256] as $name => $checksum) {
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
