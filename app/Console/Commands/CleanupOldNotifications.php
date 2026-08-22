<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Notifications\DatabaseNotification;

class CleanupOldNotifications extends Command
{
    /**
     * Nombre del comando Artisan.
     */
    protected $signature = 'notifications:cleanup';

    /**
     * Descripción.
     */
    protected $description = 'Elimina las notificaciones con más de 6 meses de antigüedad.';

    public function handle(): int
    {
        $cutoffDate = now()->subMonths(6);

        $deleted = DatabaseNotification::query()
            ->where(
                'created_at',
                '<',
                $cutoffDate
            )
            ->delete();

        $this->info(
            "Notificaciones eliminadas: {$deleted}"
        );

        $this->info(
            'Fecha límite: '
            . $cutoffDate->format('d/m/Y H:i:s')
        );

        return self::SUCCESS;
    }
}