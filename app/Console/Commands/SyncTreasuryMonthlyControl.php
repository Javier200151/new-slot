<?php

namespace App\Console\Commands;

use App\Services\Treasury\TreasuryService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;
use Throwable;

class SyncTreasuryMonthlyControl extends Command
{
    protected $signature = 'treasury:sync-monthly-control
        {--date= : Fecha de referencia YYYY-MM-DD (por defecto hoy en Europe/Madrid)}
        {--force : Permite ejecutar fuera del día 15}
        {--dry-run : Calcula los cambios sin escribir en Google Sheets}';

    protected $description = 'Completa el mes actual de Control mensual sin sobrescribir valores ya introducidos manualmente.';

    public function handle(TreasuryService $treasury): int
    {
        try {
            $date = filled($this->option('date'))
                ? CarbonImmutable::parse((string) $this->option('date'), 'Europe/Madrid')->startOfDay()
                : CarbonImmutable::now('Europe/Madrid')->startOfDay();
        } catch (Throwable) {
            $this->error('La fecha debe tener un formato válido, por ejemplo 2026-10-15.');

            return self::FAILURE;
        }

        if (! $this->option('force') && $date->day !== 15) {
            $this->info('Sin cambios: esta automatización solo escribe el día 15 de cada mes.');

            return self::SUCCESS;
        }

        try {
            $result = $treasury->syncMonthlyControl($date, (bool) $this->option('dry-run'));

            $mode = $result['dry_run'] ? 'Simulación' : 'Sincronización';
            $this->info(sprintf(
                '%s %s %d: %d celdas %s, %d ya tenían valor y %d filas no correspondían a Miembro/Recluta/Reserva/Cesado.',
                $mode,
                $result['month'],
                $result['year'],
                $result['dry_run'] ? $result['planned'] : $result['written'],
                $result['dry_run'] ? 'previstas' : 'escritas',
                $result['existing'],
                $result['ignored'],
            ));

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
