<?php

use App\Models\MemberProcedure;
use App\Models\MemberProcedureSetting;
use App\Models\MemberProcedureStep;
use App\Models\MemberProcedureStepDefinition;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions')) {
            return;
        }
        DB::transaction(function (): void {
            foreach (MemberProcedureSetting::query()->get() as $setting) {
                $query = MemberProcedureStepDefinition::query()
                    ->where('member_procedure_setting_id', $setting->id)
                    ->where('procedure_type', MemberProcedure::TYPE_REACTIVATION);
                if ((clone $query)->where('step_key', 'armasquads_upsert')->exists()) {
                    continue;
                }
                $after = (clone $query)->where('step_key', 'status_active')->first();
                if (! $after) {
                    continue;
                }
                $position = (int) $after->position + 1;
                (clone $query)->where('position', '>=', $position)->increment('position');
                MemberProcedureStepDefinition::query()->create([
                    'member_procedure_setting_id' => $setting->id,
                    'procedure_type' => MemberProcedure::TYPE_REACTIVATION,
                    'step_key' => 'armasquads_upsert',
                    'label' => 'Comprobar y sincronizar en ArmaSquads',
                    'kind' => MemberProcedureStep::KIND_AUTOMATIC,
                    'position' => $position,
                    'required' => true,
                    'is_enabled' => true,
                    'is_system' => true,
                    'depends_on' => ['status_active'],
                    'instructions' => 'Consulta y sincroniza al miembro en ArmaSquads por SteamID64 sin duplicados.',
                ]);
            }
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            MemberProcedureStepDefinition::query()
                ->where('procedure_type', MemberProcedure::TYPE_REACTIVATION)
                ->where('step_key', 'armasquads_upsert')->delete();
        }
    }
};
