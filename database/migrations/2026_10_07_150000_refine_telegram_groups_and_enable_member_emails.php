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
                'telegram_network_chat_id' => fn (Blueprint $table) => $table->string('telegram_network_chat_id', 80)->nullable(),
                'telegram_cantina_invite_url' => fn (Blueprint $table) => $table->string('telegram_cantina_invite_url', 500)->nullable(),
                'telegram_official_invite_url' => fn (Blueprint $table) => $table->string('telegram_official_invite_url', 500)->nullable(),
                'telegram_network_invite_url' => fn (Blueprint $table) => $table->string('telegram_network_invite_url', 500)->nullable(),
                'member_welcome_email_subject' => fn (Blueprint $table) => $table->string('member_welcome_email_subject', 255)->nullable(),
                'member_welcome_email_body' => fn (Blueprint $table) => $table->text('member_welcome_email_body')->nullable(),
                'reactivation_email_subject' => fn (Blueprint $table) => $table->string('reactivation_email_subject', 255)->nullable(),
                'reactivation_email_body' => fn (Blueprint $table) => $table->text('reactivation_email_body')->nullable(),
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

        $completionInstructions = 'Envía automáticamente al nuevo miembro el correo de bienvenida con los enlaces configurados para ALPHA Cantina, ALPHA Oficial y ALPHA Network.';
        $reactivationInstructions = 'Envía automáticamente al miembro reactivado el correo de bienvenida de vuelta con los enlaces configurados para ALPHA Cantina, ALPHA Oficial y ALPHA Network.';
        $reserveInstructions = 'Retira manualmente al miembro de ALPHA Oficial y ALPHA Network al pasar a RESERVA. Debe permanecer en ALPHA Cantina.';
        $departureInstructions = 'Retira manualmente al usuario de ALPHA Cantina, ALPHA Oficial y ALPHA Network.';

        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'recruitment_start')
                ->where('step_key', 'telegram_recruit_announcement')
                ->delete();

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'not_promoted')
                ->where('step_key', 'telegram_not_promoted')
                ->delete();

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'recruitment_complete')
                ->where('step_key', 'telegram_groups_email')
                ->update([
                    'kind' => 'automatic',
                    'is_enabled' => true,
                    'is_system' => true,
                    'label' => 'Enviar email de bienvenida con accesos de Telegram',
                    'instructions' => $completionInstructions,
                    'depends_on' => json_encode(['status_active']),
                    'updated_at' => now(),
                ]);

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'reactivation')
                ->where('step_key', 'telegram_groups_email')
                ->update([
                    'kind' => 'automatic',
                    'is_enabled' => true,
                    'is_system' => true,
                    'label' => 'Enviar email de bienvenida de vuelta con accesos de Telegram',
                    'instructions' => $reactivationInstructions,
                    'depends_on' => json_encode(['status_active']),
                    'position' => 7,
                    'updated_at' => now(),
                ]);

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'reactivation')
                ->where('step_key', 'google_sheets_status_sync')
                ->where('position', '<=', 7)
                ->update(['position' => 8, 'updated_at' => now()]);

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'reserve')
                ->where('step_key', 'telegram_leave_official')
                ->update([
                    'label' => 'Sacar de ALPHA Oficial y ALPHA Network',
                    'instructions' => $reserveInstructions,
                    'updated_at' => now(),
                ]);

            DB::table('member_procedure_step_definitions')
                ->whereIn('procedure_type', ['departure', 'dismissal'])
                ->where('step_key', 'telegram_leave_all')
                ->update([
                    'label' => 'Sacar de ALPHA Cantina, Oficial y Network',
                    'instructions' => $departureInstructions,
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_steps') && Schema::hasTable('member_procedures')) {
            $openProcedureIds = DB::table('member_procedures')
                ->whereIn('status', ['in_progress', 'error'])
                ->pluck('id');

            if ($openProcedureIds->isNotEmpty()) {
                $this->skipObsoleteOpenSteps($openProcedureIds, 'telegram_recruit_announcement', 'Telegram no se usa durante el reclutamiento; los reclutas están únicamente en WhatsApp.');
                $this->skipObsoleteOpenSteps($openProcedureIds, 'telegram_not_promoted', 'Los reclutas no forman parte de los grupos oficiales de Telegram; no hay ninguna retirada que realizar.');

                $this->updateOpenEmailSteps(
                    $openProcedureIds,
                    'recruitment_complete',
                    'Enviar email de bienvenida con accesos de Telegram',
                    $completionInstructions,
                );
                $this->updateOpenEmailSteps(
                    $openProcedureIds,
                    'reactivation',
                    'Enviar email de bienvenida de vuelta con accesos de Telegram',
                    $reactivationInstructions,
                    true,
                );

                $this->updateOpenManualStep($openProcedureIds, 'reserve', 'telegram_leave_official', 'Sacar de ALPHA Oficial y ALPHA Network', $reserveInstructions);
                $this->updateOpenManualStep($openProcedureIds, 'departure', 'telegram_leave_all', 'Sacar de ALPHA Cantina, Oficial y Network', $departureInstructions);
                $this->updateOpenManualStep($openProcedureIds, 'dismissal', 'telegram_leave_all', 'Sacar de ALPHA Cantina, Oficial y Network', $departureInstructions);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            $settingIds = Schema::hasTable('member_procedure_settings')
                ? DB::table('member_procedure_settings')->pluck('id')
                : collect();

            foreach ($settingIds as $settingId) {
                DB::table('member_procedure_step_definitions')->updateOrInsert(
                    [
                        'member_procedure_setting_id' => $settingId,
                        'procedure_type' => 'recruitment_start',
                        'step_key' => 'telegram_recruit_announcement',
                    ],
                    [
                        'label' => 'Anunciar la entrada del recluta en Telegram',
                        'instructions' => 'Publica automáticamente en el chat/canal configurado de Telegram la entrada del nuevo recluta. Si Telegram no está configurado, el paso pasa a manual para que el procedimiento pueda continuar sin revertir el estado interno.',
                        'kind' => 'automatic',
                        'position' => 6,
                        'required' => true,
                        'is_enabled' => true,
                        'is_system' => true,
                        'depends_on' => json_encode(['status_recruit']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );

                DB::table('member_procedure_step_definitions')->updateOrInsert(
                    [
                        'member_procedure_setting_id' => $settingId,
                        'procedure_type' => 'not_promoted',
                        'step_key' => 'telegram_not_promoted',
                    ],
                    [
                        'label' => 'Retirar al recluta de los grupos de Telegram que correspondan',
                        'instructions' => 'Retira manualmente al recluta de los grupos de Telegram que correspondan. La Bot API puede expulsar usuarios únicamente cuando NewSlot conoce su Telegram User ID; esta vinculación personal todavía no forma parte de N04.',
                        'kind' => 'manual',
                        'position' => 4,
                        'required' => true,
                        'is_enabled' => true,
                        'is_system' => true,
                        'depends_on' => json_encode(['not_promoted_validation']),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ],
                );
            }

            DB::table('member_procedure_step_definitions')
                ->whereIn('procedure_type', ['recruitment_complete', 'reactivation'])
                ->where('step_key', 'telegram_groups_email')
                ->update([
                    'kind' => 'manual',
                    'updated_at' => now(),
                ]);

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'reserve')
                ->where('step_key', 'telegram_leave_official')
                ->update([
                    'label' => 'Sacar de los grupos oficiales de Telegram',
                    'instructions' => 'Retira manualmente al miembro de los grupos oficiales de Telegram durante su periodo en reserva. La Bot API necesita el Telegram User ID del miembro para automatizar esta acción.',
                    'updated_at' => now(),
                ]);

            DB::table('member_procedure_step_definitions')
                ->whereIn('procedure_type', ['departure', 'dismissal'])
                ->where('step_key', 'telegram_leave_all')
                ->update([
                    'label' => 'Sacar de los grupos de Telegram',
                    'instructions' => 'Retira manualmente al usuario de los grupos oficiales de Telegram. La Bot API necesita el Telegram User ID del miembro para automatizar esta acción.',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('member_procedure_settings', 'telegram_network_chat_id') ? 'telegram_network_chat_id' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_cantina_invite_url') ? 'telegram_cantina_invite_url' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_official_invite_url') ? 'telegram_official_invite_url' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_network_invite_url') ? 'telegram_network_invite_url' : null,
                Schema::hasColumn('member_procedure_settings', 'member_welcome_email_subject') ? 'member_welcome_email_subject' : null,
                Schema::hasColumn('member_procedure_settings', 'member_welcome_email_body') ? 'member_welcome_email_body' : null,
                Schema::hasColumn('member_procedure_settings', 'reactivation_email_subject') ? 'reactivation_email_subject' : null,
                Schema::hasColumn('member_procedure_settings', 'reactivation_email_body') ? 'reactivation_email_body' : null,
            ]));

            if ($columns !== []) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }

    private function skipObsoleteOpenSteps($procedureIds, string $stepKey, string $reason): void
    {
        DB::table('member_procedure_steps')
            ->whereIn('member_procedure_id', $procedureIds)
            ->where('step_key', $stepKey)
            ->whereNotIn('status', ['completed', 'skipped'])
            ->update([
                'status' => 'skipped',
                'result' => json_encode(['reason' => $reason], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'last_error' => null,
                'completed_at' => now(),
                'updated_at' => now(),
            ]);
    }

    private function updateOpenEmailSteps($procedureIds, string $procedureType, string $label, string $instructions, bool $moveAfterStatus = false): void
    {
        $procedureIdsForType = DB::table('member_procedures')
            ->whereIn('id', $procedureIds)
            ->where('type', $procedureType)
            ->pluck('id');

        if ($procedureIdsForType->isEmpty()) {
            return;
        }

        $steps = DB::table('member_procedure_steps')
            ->whereIn('member_procedure_id', $procedureIdsForType)
            ->where('step_key', 'telegram_groups_email')
            ->get(['id', 'status', 'position', 'meta']);

        foreach ($steps as $step) {
            $meta = json_decode((string) ($step->meta ?? ''), true);
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['depends_on'] = ['status_active'];
            $meta['instructions'] = $instructions;

            $update = [
                'kind' => 'automatic',
                'status' => $step->status === 'manual_pending' ? 'pending' : $step->status,
                'label' => $label,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ];

            if ($moveAfterStatus) {
                $statusPosition = DB::table('member_procedure_steps')
                    ->where('member_procedure_id', DB::table('member_procedure_steps')->where('id', $step->id)->value('member_procedure_id'))
                    ->where('step_key', 'status_active')
                    ->value('position');
                if ($statusPosition !== null && (int) $step->position <= (int) $statusPosition) {
                    $update['position'] = (int) $statusPosition + 1;
                }
            }

            DB::table('member_procedure_steps')->where('id', $step->id)->update($update);
        }
    }

    private function updateOpenManualStep($procedureIds, string $procedureType, string $stepKey, string $label, string $instructions): void
    {
        $procedureIdsForType = DB::table('member_procedures')
            ->whereIn('id', $procedureIds)
            ->where('type', $procedureType)
            ->pluck('id');

        if ($procedureIdsForType->isEmpty()) {
            return;
        }

        $steps = DB::table('member_procedure_steps')
            ->whereIn('member_procedure_id', $procedureIdsForType)
            ->where('step_key', $stepKey)
            ->get(['id', 'meta']);

        foreach ($steps as $step) {
            $meta = json_decode((string) ($step->meta ?? ''), true);
            if (! is_array($meta)) {
                $meta = [];
            }
            $meta['instructions'] = $instructions;

            DB::table('member_procedure_steps')->where('id', $step->id)->update([
                'label' => $label,
                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                'updated_at' => now(),
            ]);
        }
    }
};
