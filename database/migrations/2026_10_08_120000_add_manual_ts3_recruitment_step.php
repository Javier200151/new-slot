<?php

use App\Models\MemberProcedure;
use App\Models\MemberProcedureStep;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const STEP_KEY = 'ts3_recruit';
    private const POSITION = 9;

    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        $now = now();
        $type = MemberProcedure::TYPE_RECRUITMENT_START;

        DB::table('member_procedure_settings')
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($settingId) use ($now, $type): void {
                $exists = DB::table('member_procedure_step_definitions')
                    ->where('member_procedure_setting_id', $settingId)
                    ->where('procedure_type', $type)
                    ->where('step_key', self::STEP_KEY)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('member_procedure_step_definitions')
                    ->where('member_procedure_setting_id', $settingId)
                    ->where('procedure_type', $type)
                    ->where('position', '>=', self::POSITION)
                    ->increment('position');

                DB::table('member_procedure_step_definitions')->insert([
                    'member_procedure_setting_id' => $settingId,
                    'procedure_type' => $type,
                    'step_key' => self::STEP_KEY,
                    'label' => 'Asignar en TS3 el rol RECLUTA',
                    'instructions' => 'En TeamSpeak 3, asigna manualmente al usuario el grupo o rol de RECLUTA y marca este paso como completado cuando esté confirmado.',
                    'kind' => MemberProcedureStep::KIND_MANUAL,
                    'position' => self::POSITION,
                    'required' => true,
                    'is_enabled' => true,
                    'is_system' => true,
                    'depends_on' => json_encode(['status_recruit'], JSON_THROW_ON_ERROR),
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });

        // Los procedimientos ya abiertos son snapshots. Añadimos también la tarea
        // a los reclutamientos todavía en curso para que no queden fuera del flujo manual.
        if (! Schema::hasTable('member_procedures') || ! Schema::hasTable('member_procedure_steps')) {
            return;
        }

        DB::table('member_procedures')
            ->where('type', $type)
            ->whereIn('status', [MemberProcedure::STATUS_IN_PROGRESS, MemberProcedure::STATUS_ERROR])
            ->orderBy('id')
            ->pluck('id')
            ->each(function ($procedureId) use ($now): void {
                $exists = DB::table('member_procedure_steps')
                    ->where('member_procedure_id', $procedureId)
                    ->where('step_key', self::STEP_KEY)
                    ->exists();

                if ($exists) {
                    return;
                }

                DB::table('member_procedure_steps')
                    ->where('member_procedure_id', $procedureId)
                    ->where('position', '>=', self::POSITION)
                    ->increment('position');

                DB::table('member_procedure_steps')->insert([
                    'member_procedure_id' => $procedureId,
                    'step_key' => self::STEP_KEY,
                    'label' => 'Asignar en TS3 el rol RECLUTA',
                    'kind' => MemberProcedureStep::KIND_MANUAL,
                    'status' => MemberProcedureStep::STATUS_MANUAL,
                    'position' => self::POSITION,
                    'required' => true,
                    'attempts' => 0,
                    'meta' => json_encode([
                        'depends_on' => ['status_recruit'],
                        'instructions' => 'En TeamSpeak 3, asigna manualmente al usuario el grupo o rol de RECLUTA y marca este paso como completado cuando esté confirmado.',
                    ], JSON_THROW_ON_ERROR),
                    'result' => null,
                    'last_error' => null,
                    'completed_by_user_id' => null,
                    'completed_at' => null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            });
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_steps') && Schema::hasTable('member_procedures')) {
            $procedureIds = DB::table('member_procedures')
                ->where('type', MemberProcedure::TYPE_RECRUITMENT_START)
                ->pluck('id');

            DB::table('member_procedure_steps')
                ->whereIn('member_procedure_id', $procedureIds)
                ->where('step_key', self::STEP_KEY)
                ->delete();

            DB::table('member_procedure_steps')
                ->whereIn('member_procedure_id', $procedureIds)
                ->where('position', '>', self::POSITION)
                ->decrement('position');
        }

        if (! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }

        $type = MemberProcedure::TYPE_RECRUITMENT_START;
        $settingIds = DB::table('member_procedure_step_definitions')
            ->where('procedure_type', $type)
            ->where('step_key', self::STEP_KEY)
            ->pluck('member_procedure_setting_id');

        DB::table('member_procedure_step_definitions')
            ->where('procedure_type', $type)
            ->where('step_key', self::STEP_KEY)
            ->delete();

        foreach ($settingIds as $settingId) {
            DB::table('member_procedure_step_definitions')
                ->where('member_procedure_setting_id', $settingId)
                ->where('procedure_type', $type)
                ->where('position', '>', self::POSITION)
                ->decrement('position');
        }
    }
};
