<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('member_procedure_settings')) {
            $columns = [
                'telegram_recruit_update_template' => fn (Blueprint $table) => $table->text('telegram_recruit_update_template')->nullable(),
                'telegram_recruit_entry_endings' => fn (Blueprint $table) => $table->json('telegram_recruit_entry_endings')->nullable(),
                'telegram_recruit_exit_endings' => fn (Blueprint $table) => $table->json('telegram_recruit_exit_endings')->nullable(),
                'telegram_veterancy_template' => fn (Blueprint $table) => $table->text('telegram_veterancy_template')->nullable(),
                'telegram_veterancy_endings' => fn (Blueprint $table) => $table->json('telegram_veterancy_endings')->nullable(),
                'telegram_weekly_template' => fn (Blueprint $table) => $table->text('telegram_weekly_template')->nullable(),
                'telegram_weekly_endings' => fn (Blueprint $table) => $table->json('telegram_weekly_endings')->nullable(),
                'telegram_weekly_required_weekdays' => fn (Blueprint $table) => $table->json('telegram_weekly_required_weekdays')->nullable(),
                'telegram_weekly_required_activity_type_ids' => fn (Blueprint $table) => $table->json('telegram_weekly_required_activity_type_ids')->nullable(),
                'telegram_weekly_active_event_status_id' => fn (Blueprint $table) => $table->unsignedBigInteger('telegram_weekly_active_event_status_id')->nullable(),
            ];

            foreach ($columns as $column => $add) {
                if (! Schema::hasColumn('member_procedure_settings', $column)) {
                    Schema::table('member_procedure_settings', function (Blueprint $table) use ($add): void {
                        $add($table);
                    });
                }
            }
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $activeEventStatusId = null;
        if (Schema::hasTable('event_status')) {
            $activeEventStatusId = DB::table('event_status')
                ->whereRaw('UPPER(name) = ?', ['ACTIVO'])
                ->value('id');
        }

        $requiredTypeIds = [];
        if (Schema::hasTable('activity_types')) {
            $requiredTypeIds = DB::table('activity_types')
                ->get(['id', 'name'])
                ->filter(function ($row): bool {
                    $name = mb_strtoupper(trim((string) $row->name));

                    return in_array($name, ['OPERATIVO', 'OPERATIVOS', 'OPERACIÓN', 'OPERACION'], true);
                })
                ->pluck('id')
                ->map(fn ($id): int => (int) $id)
                ->values()
                ->all();
        }

        if (Schema::hasTable('member_procedure_settings')) {
            DB::table('member_procedure_settings')->orderBy('id')->get()->each(function ($row) use ($activeEventStatusId, $requiredTypeIds): void {
                $updates = [];

                if (blank($row->telegram_recruit_update_template ?? null)) {
                    $updates['telegram_recruit_update_template'] = "Actualización de reclutas:\n\n{{cambio}}\n\n{{cierre}}";
                }
                if (blank($row->telegram_recruit_entry_endings ?? null)) {
                    $updates['telegram_recruit_entry_endings'] = json_encode([
                        ['text' => 'Como a los demás, empezaréis a ver a {{nick}} pronto en los operativos\\!'],
                    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (blank($row->telegram_recruit_exit_endings ?? null)) {
                    $updates['telegram_recruit_exit_endings'] = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (blank($row->telegram_veterancy_template ?? null)) {
                    $updates['telegram_veterancy_template'] = "{{foro_url}}\n\nLa administración tiene el orgullo de galardonar:\n\n{{veteranias}}\n\n{{cierre}}";
                }
                if (blank($row->telegram_veterancy_endings ?? null)) {
                    $updates['telegram_veterancy_endings'] = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (blank($row->telegram_weekly_template ?? null)) {
                    $updates['telegram_weekly_template'] = "ACTUALIZADA ACTIVIDAD SEMANAL\n\n☠️☠️ ACTIVIDAD SEMANAL ☠️☠️\n\n{{eventos}}\n\n{{cierre}}";
                }
                if (blank($row->telegram_weekly_endings ?? null)) {
                    $updates['telegram_weekly_endings'] = json_encode([], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
                }
                if (blank($row->telegram_weekly_required_weekdays ?? null)) {
                    $updates['telegram_weekly_required_weekdays'] = json_encode([2, 5]);
                }
                if (blank($row->telegram_weekly_required_activity_type_ids ?? null) && $requiredTypeIds !== []) {
                    $updates['telegram_weekly_required_activity_type_ids'] = json_encode($requiredTypeIds);
                }
                if (empty($row->telegram_weekly_active_event_status_id ?? null) && $activeEventStatusId) {
                    $updates['telegram_weekly_active_event_status_id'] = $activeEventStatusId;
                }

                if ($updates !== []) {
                    $updates['updated_at'] = now();
                    DB::table('member_procedure_settings')->where('id', $row->id)->update($updates);
                }
            });
        }

        $this->restoreRecruitTelegramSteps();
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->whereIn('step_key', ['telegram_recruit_update'])
                ->delete();

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'recruitment_start')
                ->where('position', '>=', 8)
                ->decrement('position');
        }

        if (Schema::hasTable('member_procedure_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('member_procedure_settings', 'telegram_recruit_update_template') ? 'telegram_recruit_update_template' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_recruit_entry_endings') ? 'telegram_recruit_entry_endings' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_recruit_exit_endings') ? 'telegram_recruit_exit_endings' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_veterancy_template') ? 'telegram_veterancy_template' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_veterancy_endings') ? 'telegram_veterancy_endings' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_weekly_template') ? 'telegram_weekly_template' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_weekly_endings') ? 'telegram_weekly_endings' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_weekly_required_weekdays') ? 'telegram_weekly_required_weekdays' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_weekly_required_activity_type_ids') ? 'telegram_weekly_required_activity_type_ids' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_weekly_active_event_status_id') ? 'telegram_weekly_active_event_status_id' : null,
            ]));

            if ($columns !== []) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }

    private function restoreRecruitTelegramSteps(): void
    {
        if (! Schema::hasTable('member_procedure_step_definitions') || ! Schema::hasTable('member_procedure_settings')) {
            return;
        }

        foreach (DB::table('member_procedure_settings')->pluck('id') as $settingId) {
            DB::table('member_procedure_step_definitions')
                ->where('member_procedure_setting_id', $settingId)
                ->where('procedure_type', 'recruitment_start')
                ->where('step_key', '!=', 'telegram_recruit_update')
                ->where('position', '>=', 7)
                ->increment('position');

            $this->upsertStep(
                (int) $settingId,
                'recruitment_start',
                'telegram_recruit_update',
                'Publicar actualización del nuevo recluta en Telegram',
                'Publica en ALPHA Network la entrada del nuevo recluta usando la plantilla configurable de Telegram.',
                7,
                ['status_recruit'],
            );

            $this->upsertStep(
                (int) $settingId,
                'not_promoted',
                'telegram_recruit_update',
                'Publicar actualización del recluta no promocionado en Telegram',
                'Publica en ALPHA Network que el recluta finaliza el proceso como NO PROMOCIONADO usando la plantilla configurable de Telegram.',
                8,
                ['status_not_promoted'],
            );
        }
    }

    private function upsertStep(int $settingId, string $procedureType, string $stepKey, string $label, string $instructions, int $position, array $dependsOn): void
    {
        DB::table('member_procedure_step_definitions')->updateOrInsert(
            [
                'member_procedure_setting_id' => $settingId,
                'procedure_type' => $procedureType,
                'step_key' => $stepKey,
            ],
            [
                'label' => $label,
                'instructions' => $instructions,
                'kind' => 'automatic',
                'position' => $position,
                'required' => true,
                'is_enabled' => true,
                'is_system' => true,
                'depends_on' => json_encode(array_values($dependsOn)),
                'created_at' => now(),
                'updated_at' => now(),
            ],
        );
    }
};
