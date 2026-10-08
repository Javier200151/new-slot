<?php

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (
            DB::connection()->pretending()
            || ! Schema::hasTable('member_procedure_settings')
            || ! Schema::hasTable('member_procedure_step_definitions')
        ) {
            return;
        }

        $definitions = [
            MemberProcedure::TYPE_RECRUITMENT_START => [
                'label' => 'Registrar al recluta en Tesorería',
                'depends_on' => ['status_recruit'],
                'instructions' => 'Añade nickname, estado RECLUTA y fecha de alta en la primera fila libre al final de Jugadores. No modifica saldos ni fórmulas y falla si detecta duplicados.',
            ],
            MemberProcedure::TYPE_RECRUITMENT_COMPLETE => [
                'label' => 'Actualizar Tesorería a Miembro y calcular importe pendiente',
                'depends_on' => ['status_active'],
                'instructions' => 'Cambia Jugadores a Miembro y calcula el importe pendiente teniendo en cuenta señal, meses consumidos y trimestre.',
            ],
            MemberProcedure::TYPE_NOT_PROMOTED => [
                'label' => 'Actualizar Tesorería a Cesado',
                'depends_on' => ['status_not_promoted'],
                'instructions' => 'En Tesorería, NO PROMOCIONADO y cualquier estado distinto de ACTIVO/RECLUTA/RESERVA se representa como Cesado.',
            ],
            MemberProcedure::TYPE_REACTIVATION => [
                'label' => 'Actualizar Tesorería a Miembro y calcular reincorporación',
                'depends_on' => ['status_active'],
                'instructions' => 'Cambia Jugadores a Miembro y calcula los meses que corresponden desde la reincorporación, respetando Reserva y el siguiente trimestre.',
            ],
            MemberProcedure::TYPE_RESERVE => [
                'label' => 'Actualizar Tesorería a Reserva',
                'depends_on' => ['status_reserve'],
                'instructions' => 'Actualiza únicamente el estado de Jugadores a Reserva.',
            ],
            MemberProcedure::TYPE_DEPARTURE => [
                'label' => 'Actualizar Tesorería a Cesado',
                'depends_on' => ['status_departed'],
                'instructions' => 'En Tesorería, BAJA se representa como Cesado.',
            ],
            MemberProcedure::TYPE_DISMISSAL => [
                'label' => 'Actualizar Tesorería a Cesado',
                'depends_on' => ['status_dismissed'],
                'instructions' => 'En Tesorería, CESADO se representa como Cesado.',
            ],
        ];

        $settingIds = DB::table('member_procedure_settings')->pluck('id');
        $now = now();

        foreach ($settingIds as $settingId) {
            foreach ($definitions as $type => $definition) {
                $exists = DB::table('member_procedure_step_definitions')
                    ->where('member_procedure_setting_id', $settingId)
                    ->where('procedure_type', $type)
                    ->where('step_key', 'treasury_player_sync')
                    ->exists();

                if ($exists) {
                    continue;
                }

                $position = ((int) DB::table('member_procedure_step_definitions')
                    ->where('member_procedure_setting_id', $settingId)
                    ->where('procedure_type', $type)
                    ->max('position')) + 1;

                DB::table('member_procedure_step_definitions')->insert([
                    'member_procedure_setting_id' => $settingId,
                    'procedure_type' => $type,
                    'step_key' => 'treasury_player_sync',
                    'label' => $definition['label'],
                    'instructions' => $definition['instructions'],
                    'kind' => MemberProcedureStep::KIND_AUTOMATIC,
                    'position' => max(1, $position),
                    'required' => true,
                    'is_enabled' => true,
                    'is_system' => true,
                    'depends_on' => json_encode($definition['depends_on'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }

        if (Schema::hasTable('member_procedures') && Schema::hasTable('member_procedure_steps')) {
            DB::table('member_procedures')
                ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
                ->orderBy('id')
                ->get(['id', 'type'])
                ->each(function ($procedure) use ($definitions, $now): void {
                    $definition = $definitions[$procedure->type] ?? null;
                    if (! $definition) {
                        return;
                    }

                    $exists = DB::table('member_procedure_steps')
                        ->where('member_procedure_id', $procedure->id)
                        ->where('step_key', 'treasury_player_sync')
                        ->exists();

                    if ($exists) {
                        return;
                    }

                    $position = ((int) DB::table('member_procedure_steps')
                        ->where('member_procedure_id', $procedure->id)
                        ->max('position')) + 1;

                    DB::table('member_procedure_steps')->insert([
                        'member_procedure_id' => $procedure->id,
                        'step_key' => 'treasury_player_sync',
                        'label' => $definition['label'],
                        'kind' => MemberProcedureStep::KIND_AUTOMATIC,
                        'status' => MemberProcedureStep::STATUS_PENDING,
                        'position' => max(1, $position),
                        'required' => true,
                        'attempts' => 0,
                        'meta' => json_encode([
                            'depends_on' => $definition['depends_on'],
                            'instructions' => $definition['instructions'],
                        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                        'result' => null,
                        'last_error' => null,
                        'completed_by_user_id' => null,
                        'completed_at' => null,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                });
        }
    }

    public function down(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        DB::table('member_procedure_step_definitions')
            ->where('step_key', 'treasury_player_sync')
            ->delete();

        if (Schema::hasTable('member_procedure_steps')) {
            DB::table('member_procedure_steps')
                ->where('step_key', 'treasury_player_sync')
                ->delete();
        }
    }
};
