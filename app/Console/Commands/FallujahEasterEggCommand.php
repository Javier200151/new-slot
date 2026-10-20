<?php

namespace App\Console\Commands;

use App\Support\FallujahEasterEgg;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class FallujahEasterEggCommand extends Command
{
    protected $signature = 'sqa:fallujah {action=status : on, off, auto o status}';

    protected $description = 'Controla la aparición excepcional del easter egg del 27 de abril';

    public function handle(): int
    {
        $action = strtolower((string) $this->argument('action'));

        if (! in_array($action, ['on', 'off', 'auto', 'status'], true)) {
            $this->error('Acción desconocida. Usa on, off, auto o status.');
            return self::FAILURE;
        }

        if (! Schema::hasTable('sqa_easter_egg_overrides')) {
            $this->error('Falta la tabla de configuración. Ejecuta php artisan migrate primero.');
            return self::FAILURE;
        }

        if ($action !== 'status') {
            DB::table('sqa_easter_egg_overrides')->updateOrInsert(
                ['key' => FallujahEasterEgg::FEATURE_KEY],
                ['mode' => $action, 'updated_at' => now(), 'created_at' => now()]
            );
            $this->info('Estado excepcional actualizado.');
        }

        $mode = FallujahEasterEgg::mode();
        $labels = ['on' => 'FORZADO ACTIVADO', 'off' => 'FORZADO DESACTIVADO', 'auto' => 'AUTOMÁTICO (solo 27 de abril)'];

        $this->line('Modo: '.$labels[$mode]);
        $this->line('Visible ahora: '.(FallujahEasterEgg::visible() ? 'SÍ' : 'NO'));
        $this->line('Fecha de referencia: '.now(FallujahEasterEgg::ZONE)->format('d/m/Y H:i').' (Europe/Madrid)');

        if ($mode === 'on') {
            $this->warn('El modo on permanece activo hasta usar off o auto.');
        }

        return self::SUCCESS;
    }
}
