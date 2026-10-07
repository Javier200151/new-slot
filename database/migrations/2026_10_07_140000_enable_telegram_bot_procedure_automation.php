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
            $missingRecruitChat = ! Schema::hasColumn('member_procedure_settings', 'telegram_recruit_chat_id');
            $missingTutorsChat = ! Schema::hasColumn('member_procedure_settings', 'telegram_tutors_chat_id');
            $missingRecruitMessage = ! Schema::hasColumn('member_procedure_settings', 'telegram_recruit_message');
            $missingTutorsMessage = ! Schema::hasColumn('member_procedure_settings', 'telegram_tutors_message');

            if ($missingRecruitChat || $missingTutorsChat || $missingRecruitMessage || $missingTutorsMessage) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($missingRecruitChat, $missingTutorsChat, $missingRecruitMessage, $missingTutorsMessage): void {
                    if ($missingRecruitChat) {
                        $table->string('telegram_recruit_chat_id', 80)->nullable();
                    }
                    if ($missingTutorsChat) {
                        $table->string('telegram_tutors_chat_id', 80)->nullable();
                    }
                    if ($missingRecruitMessage) {
                        $table->text('telegram_recruit_message')->nullable();
                    }
                    if ($missingTutorsMessage) {
                        $table->text('telegram_tutors_message')->nullable();
                    }
                });
            }
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $recruitLabel = 'Anunciar la entrada del recluta en Telegram';
        $recruitInstructions = 'Publica automáticamente en el chat/canal configurado de Telegram la entrada del nuevo recluta. Si Telegram no está configurado, el paso pasa a manual para que el procedimiento pueda continuar sin revertir el estado interno.';

        $manualInstructions = [
            'telegram_not_promoted' => 'Retira manualmente al recluta de los grupos de Telegram que correspondan. La Bot API puede expulsar usuarios únicamente cuando NewSlot conoce su Telegram User ID; esta vinculación personal todavía no forma parte de N04.',
            'telegram_leave_official' => 'Retira manualmente al miembro de los grupos oficiales de Telegram durante su periodo en reserva. La Bot API necesita el Telegram User ID del miembro para automatizar esta acción.',
            'telegram_leave_all' => 'Retira manualmente al usuario de los grupos oficiales de Telegram. La Bot API necesita el Telegram User ID del miembro para automatizar esta acción.',
        ];

        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('step_key', 'telegram_recruit_announcement')
                ->update([
                    'kind' => 'automatic',
                    'is_enabled' => true,
                    'is_system' => true,
                    'label' => $recruitLabel,
                    'instructions' => $recruitInstructions,
                    'updated_at' => now(),
                ]);

            foreach ($manualInstructions as $stepKey => $instructions) {
                DB::table('member_procedure_step_definitions')
                    ->where('step_key', $stepKey)
                    ->update([
                        'instructions' => $instructions,
                        'updated_at' => now(),
                    ]);
            }
        }

        if (Schema::hasTable('member_procedure_steps') && Schema::hasTable('member_procedures')) {
            $openProcedureIds = DB::table('member_procedures')
                ->whereIn('status', ['in_progress', 'error'])
                ->pluck('id');

            if ($openProcedureIds->isNotEmpty()) {
                $steps = DB::table('member_procedure_steps')
                    ->whereIn('member_procedure_id', $openProcedureIds)
                    ->where('step_key', 'telegram_recruit_announcement')
                    ->get(['id', 'status', 'meta']);

                foreach ($steps as $step) {
                    $meta = json_decode((string) ($step->meta ?? ''), true);
                    if (! is_array($meta)) {
                        $meta = [];
                    }
                    $meta['instructions'] = $recruitInstructions;

                    DB::table('member_procedure_steps')
                        ->where('id', $step->id)
                        ->update([
                            'kind' => 'automatic',
                            'status' => $step->status === 'manual_pending' ? 'pending' : $step->status,
                            'label' => $recruitLabel,
                            'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                            'updated_at' => now(),
                        ]);
                }

                foreach ($manualInstructions as $stepKey => $instructions) {
                    $steps = DB::table('member_procedure_steps')
                        ->whereIn('member_procedure_id', $openProcedureIds)
                        ->where('step_key', $stepKey)
                        ->get(['id', 'meta']);

                    foreach ($steps as $step) {
                        $meta = json_decode((string) ($step->meta ?? ''), true);
                        if (! is_array($meta)) {
                            $meta = [];
                        }
                        $meta['instructions'] = $instructions;

                        DB::table('member_procedure_steps')
                            ->where('id', $step->id)
                            ->update([
                                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'updated_at' => now(),
                            ]);
                    }
                }
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_step_definitions')) {
            DB::table('member_procedure_step_definitions')
                ->where('step_key', 'telegram_recruit_announcement')
                ->update([
                    'kind' => 'manual',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('member_procedure_settings', 'telegram_recruit_chat_id') ? 'telegram_recruit_chat_id' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_tutors_chat_id') ? 'telegram_tutors_chat_id' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_recruit_message') ? 'telegram_recruit_message' : null,
                Schema::hasColumn('member_procedure_settings', 'telegram_tutors_message') ? 'telegram_tutors_message' : null,
            ]));

            if ($columns !== []) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
