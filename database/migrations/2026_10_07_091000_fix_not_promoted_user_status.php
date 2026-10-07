<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::connection()->pretending() || ! Schema::hasTable('status')) {
            return;
        }

        $existing = DB::table('status')
            ->whereRaw('UPPER(TRIM(name)) = ?', ['NO PROMOCIONADO'])
            ->first();

        if ($existing) {
            $updates = [];
            if (Schema::hasColumn('status', 'is_system')) {
                $updates['is_system'] = true;
            }
            if (Schema::hasColumn('status', 'deleted_at')) {
                $updates['deleted_at'] = null;
            }
            if ($updates !== []) {
                DB::table('status')->where('id', $existing->id)->update($updates);
            }
        } else {
            $row = ['name' => 'NO PROMOCIONADO'];
            if (Schema::hasColumn('status', 'color')) {
                $row['color'] = '#ef4444';
            }
            if (Schema::hasColumn('status', 'is_system')) {
                $row['is_system'] = true;
            }
            if (Schema::hasColumn('status', 'created_at')) {
                $row['created_at'] = now();
            }
            if (Schema::hasColumn('status', 'updated_at')) {
                $row['updated_at'] = now();
            }
            DB::table('status')->insert($row);
        }

        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'not_promoted')
                ->where('step_key', 'status_not_promoted')
                ->update([
                    'label' => 'Cambiar estado a NO PROMOCIONADO',
                    'instructions' => 'Cambia el estado del usuario de RECLUTA a NO PROMOCIONADO. Al salir de RECLUTA, el Área de tutores cierra automáticamente el periodo con resultado NO PROMOCIONADO.',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_steps') && Schema::hasTable('member_procedures')) {
            DB::table('member_procedure_steps as s')
                ->join('member_procedures as p', 'p.id', '=', 's.member_procedure_id')
                ->where('p.type', 'not_promoted')
                ->where('s.step_key', 'status_not_promoted')
                ->whereIn('p.status', ['in_progress', 'error'])
                ->update([
                    's.label' => 'Cambiar estado a NO PROMOCIONADO',
                    's.updated_at' => now(),
                ]);
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'not_promoted')
                ->where('step_key', 'status_not_promoted')
                ->update([
                    'label' => 'Cambiar estado a BAJA y cerrar el reclutamiento como NO PROMOCIONADO',
                    'instructions' => 'Cambia el estado del usuario a BAJA. Al salir de RECLUTA, el Área de tutores cierra automáticamente el periodo con resultado NO PROMOCIONADO.',
                    'updated_at' => now(),
                ]);
        }
    }
};
