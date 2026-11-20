<?php

namespace App\Console\Commands;

use App\Services\ChangelogNotificationService;
use Illuminate\Console\Command;

class NotifyChangelogPublications extends Command
{
    protected $signature = 'changelog:notify-publications';
    protected $description = 'Notifica los nuevos changelogs cuya fecha de publicación ya ha llegado';

    public function handle(ChangelogNotificationService $service): int
    {
        $sent = $service->notifyDue();
        $this->info("Notificaciones de changelog enviadas: {$sent}");
        return self::SUCCESS;
    }
}
