<?php

namespace App\Console\Commands;

use App\Models\Activity;
use App\Models\ActivityPboExport;
use App\Services\ActivityPboExportService;
use Illuminate\Console\Command;

class SyncActivityPbos extends Command
{
    protected $signature = 'sqa:sync-pbos {--limit=20 : Maximum exports to process}';
    protected $description = 'Copy saved Arma 3 PBOs and their addon HTML to the Windows SMB share';

    public function handle(ActivityPboExportService $service): int
    {
        if (! config('pbo_sync.enabled')) {
            $this->warn('Sincronización inactiva. Configura SQA_PBO_SYNC_ENABLED=true.');
            return self::SUCCESS;
        }

        // Include PBOs that were uploaded before this feature was installed.
        Activity::query()->whereNotNull('pbo_path')->whereDoesntHave('pboExport')
            ->orderBy('id')->limit(100)->get()
            ->each(fn (Activity $activity) => $service->queue($activity));

        $limit = max(1, min(200, (int) $this->option('limit')));
        $exports = ActivityPboExport::query()
            ->whereIn('status', ['pending', 'error'])
            ->orderBy('updated_at')->limit($limit)->get();

        $ok = 0;
        foreach ($exports as $export) {
            $ok += (int) $service->sync($export);
        }

        $this->line("Transferencias completadas: {$ok}/{$exports->count()}.");
        return self::SUCCESS;
    }
}
