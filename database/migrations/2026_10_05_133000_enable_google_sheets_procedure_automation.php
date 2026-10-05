<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_steps') || ! Schema::hasTable('member_procedures')) {
            return;
        }

        $steps = DB::table('member_procedure_steps as s')
            ->join('member_procedures as p', 'p.id', '=', 's.member_procedure_id')
            ->whereIn('s.step_key', ['google_sheets_transfer', 'google_sheets_status_sync'])
            ->get([
                's.id',
                's.step_key',
                's.kind',
                's.status',
                's.meta',
                'p.type as procedure_type',
            ]);

        foreach ($steps as $step) {
            $updates = ['kind' => 'automatic', 'updated_at' => now()];

            if (! in_array((string) $step->status, ['completed', 'skipped'], true)) {
                $updates['status'] = 'pending';
                $updates['last_error'] = null;
                $updates['completed_at'] = null;
                $updates['completed_by_user_id'] = null;
            }

            $meta = json_decode((string) ($step->meta ?? '{}'), true);
            $meta = is_array($meta) ? $meta : [];

            if ((string) $step->step_key === 'google_sheets_transfer') {
                $meta['depends_on'] = ['status_active'];
                $meta['instructions'] = 'Automático con Service Account. ID Web = ID del usuario. INGRESO = primera entrada en RECLUTA. FECHA CALAVERA = Miembro desde / primera transición RECLUTA → ACTIVO. Si falla, los datos personales NO se eliminan y puedes usar la fila manual de contingencia.';
            } else {
                $meta['depends_on'] = match ((string) $step->procedure_type) {
                    'recruitment_complete' => ['google_sheets_transfer', 'status_active'],
                    'reactivation' => ['status_active'],
                    'reserve' => ['status_reserve'],
                    'departure' => ['status_departed'],
                    'dismissal' => ['status_dismissed'],
                    default => (array) ($meta['depends_on'] ?? []),
                };
            }

            $updates['meta'] = json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

            DB::table('member_procedure_steps')->where('id', $step->id)->update($updates);
        }
    }

    public function down(): void
    {
        // No revertimos ejecuciones históricas ni pasos que ya hayan podido
        // completarse automáticamente contra Google Sheets.
    }
};
