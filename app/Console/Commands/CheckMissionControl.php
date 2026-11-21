<?php

namespace App\Console\Commands;

use App\Services\MissionControl\MissionControlClient;
use App\Services\MissionControl\MissionControlException;
use Illuminate\Console\Command;

/** A read-only connectivity test. It never sends start/stop orders. */
class CheckMissionControl extends Command
{
    protected $signature = 'sqa:mission-control-check';
    protected $description = 'Comprueba la conexión HTTPS firmada a SQA Mission Control, sin ejecutar acciones';

    public function handle(MissionControlClient $client): int
    {
        if ($error = $client->configError()) {
            $this->error($error);
            return self::FAILURE;
        }
        try {
            $health = $client->health();
            $servers = $client->servers();
            $this->info('HTTPS / CA / autenticación: correctos.');
            $this->line('Versión: '.($health['version'] ?? 'desconocida'));
            $this->line('Modo: '.($health['modo'] ?? 'desconocido'));
            $this->line('Solo lectura: '.(! empty($health['solo_lectura']) ? 'sí' : 'no'));
            foreach ($servers as $server) {
                $this->line(($server['nombre'] ?? $server['id'] ?? 'Servidor').' — '.($server['estado'] ?? 'sin estado'));
            }
            return self::SUCCESS;
        } catch (MissionControlException $e) {
            $this->error($e->getMessage());
            return self::FAILURE;
        }
    }
}
