<?php

namespace App\Jobs;

use App\Models\ActivityPboExport;
use App\Services\ActivityPboExportService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use RuntimeException;

/** Event driven: a queue worker sleeps while idle; no periodic filesystem scan. */
class SyncActivityPboExport implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 5;
    public int $timeout = 900;

    public function __construct(public int $exportId)
    {
        $this->onConnection('pbo')->onQueue('pbo-sync');
        $this->afterCommit();
    }

    public function middleware(): array
    {
        // Guard one Windows publication per export key even if the same job is queued twice.
        return [(new WithoutOverlapping('sqa-pbo-export-' . $this->exportId))
            ->releaseAfter(30)->expireAfter(960)];
    }

    public function backoff(): array
    {
        return [30, 120, 600, 1800];
    }

    public function handle(ActivityPboExportService $service): void
    {
        $export = ActivityPboExport::query()->find($this->exportId);
        if (! $export || $export->status === 'synced') {
            return;
        }
        if (! config('pbo_sync.enabled')) {
            // Keep it pending: retry manually once the infrastructure is configured.
            return;
        }
        if (! $service->sync($export)) {
            throw new RuntimeException((string) ($export->fresh()?->last_error ?: 'No se pudo transferir el PBO.'));
        }
    }
}
