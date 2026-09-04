<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Services\CampaignAarService;
use Illuminate\Console\Command;

class BackfillCampaignAars extends Command
{
    protected $signature = 'campaign-aars:backfill {--notify : Notificar también a los mandos globales de eventos históricos}';

    protected $description = 'Crea AAR pendientes para eventos de campaña que ya están FINALIZADOS.';

    public function handle(CampaignAarService $service): int
    {
        $created = 0;
        $existing = 0;

        Event::query()
            ->whereHas(
                'eventStatus',
                fn ($query) => $query->where('name', 'FINALIZADO'),
            )
            ->whereHas(
                'activity',
                fn ($query) => $query->whereNotNull('campaign_id'),
            )
            ->with([
                'eventStatus',
                'activity.campaign',
                'slots.user',
                'slots.ally',
                'slots.slotType',
            ])
            ->orderBy('id')
            ->chunkById(100, function ($events) use ($service, &$created, &$existing): void {
                foreach ($events as $event) {
                    $aar = $service->ensureForFinalizedEvent(
                        $event,
                        (bool) $this->option('notify'),
                    );

                    if (! $aar) {
                        continue;
                    }

                    if ($aar->wasRecentlyCreated) {
                        $created++;
                    } else {
                        $existing++;
                    }
                }
            });

        $this->info("AAR nuevos: {$created}. Ya existentes: {$existing}.");

        if (! $this->option('notify')) {
            $this->line('No se enviaron notificaciones a eventos históricos.');
        }

        return self::SUCCESS;
    }
}
