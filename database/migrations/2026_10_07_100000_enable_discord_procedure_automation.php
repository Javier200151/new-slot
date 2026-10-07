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
            $missingGuild = ! Schema::hasColumn('member_procedure_settings', 'discord_guild_id');
            $missingRecruit = ! Schema::hasColumn('member_procedure_settings', 'discord_recruit_role_id');
            $missingAlpha = ! Schema::hasColumn('member_procedure_settings', 'discord_alpha_role_id');
            $missingReserve = ! Schema::hasColumn('member_procedure_settings', 'discord_reserve_role_id');

            if ($missingGuild || $missingRecruit || $missingAlpha || $missingReserve) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($missingGuild, $missingRecruit, $missingAlpha, $missingReserve): void {
                    if ($missingGuild) {
                        $table->string('discord_guild_id', 24)->nullable();
                    }
                    if ($missingRecruit) {
                        $table->string('discord_recruit_role_id', 24)->nullable();
                    }
                    if ($missingAlpha) {
                        $table->string('discord_alpha_role_id', 24)->nullable();
                    }
                    if ($missingReserve) {
                        $table->string('discord_reserve_role_id', 24)->nullable();
                    }
                });
            }
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $definitions = [
            'discord_recruit' => [
                'label' => 'Actualizar los roles de Discord a RECLUTA',
                'instructions' => 'Asigna automáticamente el rol RECLUTA en Discord y retira ALPHA/RESERVA si estuvieran presentes. Requiere Discord ID numérico en la ficha del usuario.',
            ],
            'discord_alpha' => [
                'label' => 'Cambiar en Discord el rol RECLUTA por ALPHA',
                'instructions' => 'Retira automáticamente RECLUTA/RESERVA y asigna ALPHA en Discord. La operación se verifica después de ejecutarse.',
            ],
            'discord_not_promoted' => [
                'label' => 'Retirar el rol/acceso de RECLUTA en Discord',
                'instructions' => 'Retira automáticamente los roles RECLUTA, ALPHA y RESERVA gestionados por NewSlot. Si el usuario ya no está en el servidor, el paso se considera completado.',
            ],
            'discord_reactivation' => [
                'label' => 'Cambiar en Discord RESERVA por ALPHA',
                'instructions' => 'Retira automáticamente RESERVA/RECLUTA y asigna ALPHA en Discord.',
            ],
            'discord_reserve' => [
                'label' => 'Cambiar en Discord ALPHA por RESERVA',
                'instructions' => 'Retira automáticamente ALPHA/RECLUTA y asigna RESERVA en Discord.',
            ],
            'discord_departure' => [
                'label' => 'Retirar roles/acceso de Discord',
                'instructions' => 'Retira automáticamente los roles RECLUTA, ALPHA y RESERVA gestionados por NewSlot. En un CESE con baneo solicitado, Discord se banea automáticamente en este mismo paso.',
            ],
        ];

        if (Schema::hasTable('member_procedure_step_definitions')) {
            foreach ($definitions as $stepKey => $definition) {
                DB::table('member_procedure_step_definitions')
                    ->where('step_key', $stepKey)
                    ->update([
                        'kind' => 'automatic',
                        'is_enabled' => true,
                        'is_system' => true,
                        'label' => $definition['label'],
                        'instructions' => $definition['instructions'],
                        'updated_at' => now(),
                    ]);
            }

            DB::table('member_procedure_step_definitions')
                ->where('procedure_type', 'dismissal')
                ->where('step_key', 'ban_if_required')
                ->update([
                    'label' => 'Aplicar los bloqueos/baneos pendientes en otros servicios',
                    'instructions' => 'Discord se gestiona automáticamente. Si el cese requiere bloqueo, aplica aquí los baneos que sigan pendientes en otros servicios. Si no se solicitó ban, este paso se omite automáticamente.',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_steps') && Schema::hasTable('member_procedures')) {
            $openProcedureIds = DB::table('member_procedures')
                ->whereIn('status', ['in_progress', 'error'])
                ->pluck('id');

            if ($openProcedureIds->isNotEmpty()) {
                foreach ($definitions as $stepKey => $definition) {
                    $steps = DB::table('member_procedure_steps')
                        ->whereIn('member_procedure_id', $openProcedureIds)
                        ->where('step_key', $stepKey)
                        ->get(['id', 'status', 'meta']);

                    foreach ($steps as $step) {
                        $meta = json_decode((string) ($step->meta ?? ''), true);
                        if (! is_array($meta)) {
                            $meta = [];
                        }
                        $meta['instructions'] = $definition['instructions'];

                        DB::table('member_procedure_steps')
                            ->where('id', $step->id)
                            ->update([
                                'kind' => 'automatic',
                                'status' => $step->status === 'manual_pending' ? 'pending' : $step->status,
                                'label' => $definition['label'],
                                'meta' => json_encode($meta, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                'updated_at' => now(),
                            ]);
                    }
                }

                $dismissalIds = DB::table('member_procedures')
                    ->whereIn('id', $openProcedureIds)
                    ->where('type', 'dismissal')
                    ->pluck('id');

                if ($dismissalIds->isNotEmpty()) {
                    $steps = DB::table('member_procedure_steps')
                        ->whereIn('member_procedure_id', $dismissalIds)
                        ->where('step_key', 'ban_if_required')
                        ->get(['id', 'meta']);

                    foreach ($steps as $step) {
                        $meta = json_decode((string) ($step->meta ?? ''), true);
                        if (! is_array($meta)) {
                            $meta = [];
                        }
                        $meta['instructions'] = 'Discord se gestiona automáticamente. Si el cese requiere bloqueo, aplica aquí los baneos que sigan pendientes en otros servicios. Si no se solicitó ban, este paso se omite automáticamente.';

                        DB::table('member_procedure_steps')
                            ->where('id', $step->id)
                            ->update([
                                'label' => 'Aplicar los bloqueos/baneos pendientes en otros servicios',
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
                ->whereIn('step_key', [
                    'discord_recruit',
                    'discord_alpha',
                    'discord_not_promoted',
                    'discord_reactivation',
                    'discord_reserve',
                    'discord_departure',
                ])
                ->update([
                    'kind' => 'manual',
                    'updated_at' => now(),
                ]);
        }

        if (Schema::hasTable('member_procedure_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('member_procedure_settings', 'discord_guild_id') ? 'discord_guild_id' : null,
                Schema::hasColumn('member_procedure_settings', 'discord_recruit_role_id') ? 'discord_recruit_role_id' : null,
                Schema::hasColumn('member_procedure_settings', 'discord_alpha_role_id') ? 'discord_alpha_role_id' : null,
                Schema::hasColumn('member_procedure_settings', 'discord_reserve_role_id') ? 'discord_reserve_role_id' : null,
            ]));

            if ($columns !== []) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
