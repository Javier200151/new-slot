<?php

namespace App\Console\Commands;

use App\Services\BirthdayNotificationService;
use Illuminate\Console\Command;

class NotifyCommunityBirthdays extends Command
{
    protected $signature = 'community:birthdays';

    protected $description = 'Envía las notificaciones de cumpleaños de miembros y reservas.';

    public function handle(BirthdayNotificationService $service): int
    {
        $count = $service->notifyToday();

        $this->info("Cumpleaños notificados: {$count}");

        return self::SUCCESS;
    }
}
