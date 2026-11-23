<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Models\Campaign;
use App\Services\ActivityPboExportService;
use App\Support\ActivityPbo;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/** Dry-run by default. Reconciling files requires explicit --apply. */
final class SharePersistentCampaignPbos extends Command
{
    protected $signature = 'sqa:share-pbos {--apply : Apply the reviewed changes (make a database backup first)} {--worker-stopped : Confirm the PBO sync worker is stopped and no manual sync is running}';
    protected $description = 'Audit/reconcile PBOs of persistent campaigns, preserving old copies';

    public function handle(ActivityPboExportService $exports): int
    {
        if ($this->option('apply') && config('pbo_sync.enabled') && ! $this->option('worker-stopped')) {
            $this->error('Detén web-pbo-worker-1 y confirma con --worker-stopped. No ejecutes otra sincronización durante la conciliación.');
            return self::FAILURE;
        }
        $disk = Storage::disk('local');
        $backupFolder = 'activity-pbos-replaced/' . now()->format('Ymd_His');
        $changed = 0;
        $conflicts = 0;
        foreach (Campaign::query()->where('persistent', true)->orderBy('id')->get() as $campaign) {
            $activities = Activity::withTrashed()->where('campaign_id', $campaign->id)
                ->orderByDesc('pbo_uploaded_at')->orderByDesc('id')->get();
            if ($activities->count() < 2) { continue; }
            $withFiles = $activities->filter(fn (Activity $a) => filled($a->pbo_path));
            if ($withFiles->isEmpty()) { continue; }
            $paths = $withFiles->pluck('pbo_path')->unique();
            $filenames = $withFiles->map(fn (Activity $a) => basename((string) $a->pbo_path))->unique();
            $this->newLine();
            $this->line('Campaña persistente #' . $campaign->id . ': ' . $campaign->name);
            $rows = [];
            foreach ($activities as $a) {
                $path = $a->pbo_path;
                $hash = $path && $disk->exists($path) ? hash_file('sha256', $disk->path($path)) : 'SIN PBO';
                $rows[] = [$a->id, $a->name, $path ? basename($path) : '-', $a->pbo_uploaded_at?->format('Y-m-d H:i:s') ?? '-',
                    $a->pbo_uploaded_by ?? '-', $a->pbo_size_bytes ?? '-', $hash];
            }
            $this->table(['ID', 'Actividad', 'PBO', 'Subido', 'Por', 'Bytes', 'SHA-256'], $rows);
            $hashes = collect($rows)->pluck(6)->reject(fn ($value) => $value === 'SIN PBO')->unique();
            if ($hashes->count() > 1) {
                $this->warn('Los PBO tienen contenidos diferentes. Se conservarán respaldos si aplicas la conciliación.');
            }
            if ($paths->count() === 1 && $withFiles->count() === $activities->count()
                && $activities->every(fn ($a) => $a->pbo_key === 'campaign:' . $campaign->id)) {
                $this->info('Ya comparten exactamente el mismo archivo.');
                continue;
            }
            if ($filenames->count() !== 1) {
                $this->error('Nombres de PBO distintos. No se unifica: podría reiniciar el progreso ALiVE.');
                $conflicts++;
                continue;
            }
            $winner = $withFiles->first(); // Most recently uploaded; missing timestamp = oldest.
            if (! ActivityPbo::isOwnStoredPath((string) $winner->pbo_path) || ! $disk->exists($winner->pbo_path)) {
                $this->error('Falta el archivo ganador; no se modifica la campaña.');
                $conflicts++;
                continue;
            }
            $this->warn('Ganador: #' . $winner->id . ' · ' . $winner->pbo_path);
            if (! $this->option('apply')) {
                $this->comment('Simulación: no se ha realizado ningún cambio.');
                continue;
            }

            $losingPaths = $paths->reject(fn ($p) => $p === $winner->pbo_path)->values();
            $backups = [];
            try {
                foreach ($losingPaths as $path) {
                    if (! ActivityPbo::isOwnStoredPath((string) $path) || ! $disk->exists($path)) {
                        throw new \RuntimeException('Falta un PBO original. Cancelado: ' . $path);
                    }
                    $dest = $backupFolder . '/' . str_replace('/', '__', $path);
                    if (! $disk->copy($path, $dest)) {
                        throw new \RuntimeException('No se pudo preservar el PBO antiguo: ' . $path);
                    }
                    $html = dirname($path) . '/' . \App\Support\ActivityAddonsHtml::htmlFilename(basename($path));
                    if ($disk->exists($html) && ! $disk->copy($html, $backupFolder . '/' . str_replace('/', '__', $html))) {
                        throw new \RuntimeException('No se pudo preservar el HTML antiguo: ' . $html);
                    }
                    $backups[] = [$path, $dest];
                }
                DB::transaction(function () use ($activities, $winner, $campaign, $exports): void {
                    $key = 'campaign:' . $campaign->id;
                    $oldKeys = $activities->pluck('pbo_key')->filter()->unique()->reject(fn ($k) => $k === $key)->values();
                    $groupExport = ActivityPboExport::query()->where('pbo_key', $key)->first();
                    if (! $groupExport) {
                        $prior = ActivityPboExport::query()->whereIn('pbo_key', $oldKeys->all())
                            ->orderByRaw("CASE WHEN status = 'synced' THEN 0 ELSE 1 END")
                            ->orderByDesc('id')->first();
                        if ($prior) { $prior->forceFill(['pbo_key' => $key])->save(); }
                    }
                    ActivityPboExport::query()->whereIn('pbo_key', $oldKeys->all())->delete();
                    if ($winner->pbo_key !== $key) {
                        $winner->forceFill(['pbo_key' => $key])->save();
                    }
                    foreach ($activities as $a) {
                        if ($a->id === $winner->id) { continue; }
                        $a->forceFill([
                            'pbo_key' => $key, 'pbo_path' => $winner->pbo_path,
                            'pbo' => $winner->pbo, 'pbo_size_bytes' => $winner->pbo_size_bytes,
                            'pbo_uploaded_by' => $winner->pbo_uploaded_by,
                            'pbo_uploaded_at' => $winner->pbo_uploaded_at,
                        ])->save();
                    }
                    $exports->regenerate($winner, false, true);
                });
                foreach ($backups as [$oldPath, $storedBackup]) {
                    // The backup has been verified before unreferenced originals are cleared.
                    $backupHash = hash_file('sha256', $disk->path($storedBackup));
                    if ($disk->exists($oldPath) && $backupHash !== hash_file('sha256', $disk->path($oldPath))) {
                        throw new \RuntimeException('El respaldo no coincide; se ha conservado el archivo original.');
                    }
                    if (! Activity::withTrashed()->where('pbo_path', $oldPath)->exists()) {
                        $html = dirname($oldPath) . '/' . \App\Support\ActivityAddonsHtml::htmlFilename(basename($oldPath));
                        $disk->delete([$oldPath, $html]);
                    }
                }
                $this->info('Conciliada. Respaldos en storage/app/private/' . $backupFolder);
                $changed++;
            } catch (Throwable $e) {
                $this->error('Error: ' . $e->getMessage());
                $conflicts++;
            }
        }
        $this->newLine();
        $this->line($this->option('apply') ? "Campañas conciliadas: {$changed}; incidencias: {$conflicts}."
            : "Simulación finalizada; incidencias que requieren revisión: {$conflicts}.");
        return $conflicts ? self::FAILURE : self::SUCCESS;
    }
}
