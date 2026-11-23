<?php

namespace App\Console\Commands;

use App\Services\MissionControl\MissionControlTelegramMonitor;
use Illuminate\Console\Command;
use Throwable;

class NotifyMissionControlTelegram extends Command
{
    protected $signature = 'sqa:mission-control-telegram {--test : Enviar un mensaje de prueba al grupo configurado}';

    protected $description = 'Envía al Telegram de servidores las comprobaciones y avisos de Mission Control.';

    public function handle(MissionControlTelegramMonitor $monitor): int
    {
        try {
            if ($this->option('test')) {
                $monitor->sendTest();
                $this->info('Mensaje de prueba enviado al grupo de servidores.');
                return self::SUCCESS;
            }
            $counts = $monitor->run();
            $this->line(sprintf('Avisos enviados: %d · omitidos: %d · errores: %d', $counts['sent'], $counts['skipped'], $counts['errors']));
            return $counts['errors'] > 0 ? self::FAILURE : self::SUCCESS;
        } catch (Throwable $e) {
            report($e);
            $this->error('No se pudo completar la supervisión de Mission Control. Consulta el registro de Laravel.');
            return self::FAILURE;
        }
    }
}
