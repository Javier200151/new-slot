<?php

namespace App\Console\Commands;

use App\Models\Event;
use App\Models\SlotType;
use App\Support\OrbatEditorProfiler;
use Illuminate\Console\Command;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

class ProfileEventOrbat extends Command
{
    protected $signature = 'orbat:profile {event : ID del evento} {--json : Devuelve solo JSON}';

    protected $description = 'Mide tamaño, consultas y memoria del ORBAT de un evento sin modificar datos.';

    public function handle(): int
    {
        $queries = 0;
        $queryMs = 0.0;

        DB::listen(function (QueryExecuted $query) use (&$queries, &$queryMs): void {
            $queries++;
            $queryMs += (float) $query->time;
        });

        $memoryBefore = memory_get_usage(true);
        $peakBefore = memory_get_peak_usage(true);
        $startedAt = hrtime(true);

        $event = Event::query()
            ->withCount(['slots', 'reservations'])
            ->findOrFail((int) $this->argument('event'));

        $orbat = is_array($event->orbat) ? $event->orbat : ['groups' => []];
        $metrics = OrbatEditorProfiler::analyze($orbat);

        $slotTypeIds = [];
        foreach (($orbat['groups'] ?? []) as $group) {
            foreach (($group['slots'] ?? []) as $slot) {
                $id = (int) ($slot['slot_type_id'] ?? 0);
                if ($id > 0) {
                    $slotTypeIds[$id] = true;
                }
            }
        }

        if ($slotTypeIds !== []) {
            SlotType::query()
                ->whereIn('id', array_keys($slotTypeIds))
                ->get(['id', 'name', 'image']);
        }

        $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
        $memoryAfter = memory_get_usage(true);
        $peakAfter = memory_get_peak_usage(true);
        $warnSlots = max(1, (int) config('newslot.orbat_editor.warn_slots', 120));
        $configuredMemoryLimit = (string) config('newslot.php_memory_limit', '256M');
        $effectiveMemoryLimit = (string) ini_get('memory_limit');
        $configuredMemoryBytes = OrbatEditorProfiler::iniBytes($configuredMemoryLimit);
        $effectiveMemoryBytes = OrbatEditorProfiler::iniBytes($effectiveMemoryLimit);

        $warnings = [];

        if ($metrics['slots'] >= $warnSlots) {
            $warnings[] = "ORBAT grande: {$metrics['slots']} slots (umbral {$warnSlots}).";
        }

        if (
            $configuredMemoryBytes !== null
            && $configuredMemoryBytes > 0
            && $effectiveMemoryBytes !== null
            && $effectiveMemoryBytes !== -1
            && $effectiveMemoryBytes < $configuredMemoryBytes
        ) {
            $warnings[] = "memory_limit efectivo {$effectiveMemoryLimit}, inferior al configurado {$configuredMemoryLimit}.";
        }

        $result = [
            'event_id' => (int) $event->id,
            'event_name' => (string) $event->name,
            ...$metrics,
            'current_assignments' => (int) $event->slots_count,
            'current_reservations' => (int) $event->reservations_count,
            'queries' => $queries,
            'query_ms' => round($queryMs, 2),
            'elapsed_ms' => round($elapsedMs, 2),
            'configured_memory_limit' => $configuredMemoryLimit,
            'memory_limit' => $effectiveMemoryLimit,
            'memory_before_bytes' => $memoryBefore,
            'memory_after_bytes' => $memoryAfter,
            'memory_delta_bytes' => max(0, $memoryAfter - $memoryBefore),
            'peak_delta_bytes' => max(0, $peakAfter - $peakBefore),
            'warnings' => $warnings,
        ];

        if ($this->option('json')) {
            $this->line((string) json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        }

        $this->info("Perfil ORBAT · evento #{$event->id} · {$event->name}");
        $this->table(
            ['Métrica', 'Valor'],
            [
                ['Grupos', $result['groups']],
                ['Slots', $result['slots']],
                ['Slots visibles', $result['visible_slots']],
                ['Slots ocultos', $result['hidden_slots']],
                ['Tipos de slot', $result['slot_types']],
                ['Asignaciones actuales', $result['current_assignments']],
                ['Reservas actuales', $result['current_reservations']],
                ['JSON ORBAT', OrbatEditorProfiler::bytes($result['json_bytes'])],
                ['Componentes Filament estimados', $result['estimated_filament_components']],
                ['Consultas', $result['queries'] . ' · ' . $result['query_ms'] . ' ms SQL'],
                ['Tiempo total', $result['elapsed_ms'] . ' ms'],
                ['Memoria añadida', OrbatEditorProfiler::bytes($result['memory_delta_bytes'])],
                ['Pico añadido', OrbatEditorProfiler::bytes($result['peak_delta_bytes'])],
                ['memory_limit configurado', $result['configured_memory_limit']],
                ['memory_limit efectivo', $result['memory_limit']],
            ],
        );

        foreach ($result['warnings'] as $warning) {
            $this->warn($warning);
        }

        $this->newLine();
        $this->comment('Este comando es de solo lectura. Para perfilar cada apertura del modal: ORBAT_EDITOR_PROFILE=true.');

        return self::SUCCESS;
    }
}
