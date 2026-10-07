<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('homepage_settings')) {
            $missingX = ! Schema::hasColumn('homepage_settings', 'x_url');
            $missingYoutube = ! Schema::hasColumn('homepage_settings', 'youtube_url');
            $missingDiscord = ! Schema::hasColumn('homepage_settings', 'discord_invite_url');
            $missingAuto = ! Schema::hasColumn('homepage_settings', 'discord_invite_auto_refresh');
            $missingRefreshed = ! Schema::hasColumn('homepage_settings', 'discord_invite_refreshed_at');
            $missingExpires = ! Schema::hasColumn('homepage_settings', 'discord_invite_expires_at');

            if ($missingX || $missingYoutube || $missingDiscord || $missingAuto || $missingRefreshed || $missingExpires) {
                Schema::table('homepage_settings', function (Blueprint $table) use ($missingX, $missingYoutube, $missingDiscord, $missingAuto, $missingRefreshed, $missingExpires): void {
                    if ($missingX) {
                        $table->string('x_url')->nullable()->after('instagram_url');
                    }
                    if ($missingYoutube) {
                        $table->string('youtube_url')->nullable()->after('x_url');
                    }
                    if ($missingDiscord) {
                        $table->text('discord_invite_url')->nullable()->after('youtube_url');
                    }
                    if ($missingAuto) {
                        $table->boolean('discord_invite_auto_refresh')->default(false)->after('discord_invite_url');
                    }
                    if ($missingRefreshed) {
                        $table->timestamp('discord_invite_refreshed_at')->nullable()->after('discord_invite_auto_refresh');
                    }
                    if ($missingExpires) {
                        $table->timestamp('discord_invite_expires_at')->nullable()->after('discord_invite_refreshed_at');
                    }
                });
            }

            DB::table('homepage_settings')->update([
                'x_url' => DB::raw("COALESCE(x_url, 'https://x.com/SquadALPHA_ES')"),
                'youtube_url' => DB::raw("COALESCE(youtube_url, 'https://www.youtube.com/c/SquadALPHA')"),
                'discord_invite_url' => DB::raw("COALESCE(discord_invite_url, 'https://discord.com/login?redirect_to=%2Fchannels%2F438069558595813417%2F476062464891551744')"),
            ]);
        }

        if (Schema::hasTable('member_procedure_settings')) {
            $missingChannel = ! Schema::hasColumn('member_procedure_settings', 'discord_invite_channel_id');
            $missingBotNick = ! Schema::hasColumn('member_procedure_settings', 'discord_bot_nickname');
            $missingPrefix = ! Schema::hasColumn('member_procedure_settings', 'discord_alpha_nickname_prefix');

            if ($missingChannel || $missingBotNick || $missingPrefix) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($missingChannel, $missingBotNick, $missingPrefix): void {
                    if ($missingChannel) {
                        $table->string('discord_invite_channel_id', 24)->nullable();
                    }
                    if ($missingBotNick) {
                        $table->string('discord_bot_nickname', 32)->nullable();
                    }
                    if ($missingPrefix) {
                        $table->string('discord_alpha_nickname_prefix', 16)->default('[=ALPHA=] ');
                    }
                });
            }
        }

        if (DB::connection()->pretending()) {
            return;
        }

        $definitions = [
            'discord_recruit' => [
                'label' => 'Actualizar Discord a RECLUTA',
                'instructions' => 'Asigna RECLUTA, retira ALPHA/RESERVA y normaliza el apodo al nick de NewSlot sin la etiqueta ALPHA.',
            ],
            'discord_alpha' => [
                'label' => 'Cambiar Discord a ALPHA y actualizar apodo',
                'instructions' => 'Retira RECLUTA/RESERVA, asigna ALPHA y cambia el apodo del servidor al formato configurado, por defecto [=ALPHA=] Nick.',
            ],
            'discord_not_promoted' => [
                'label' => 'Retirar acceso de RECLUTA en Discord',
                'instructions' => 'Retira los roles RECLUTA, ALPHA y RESERVA gestionados por NewSlot y elimina la etiqueta ALPHA del apodo si el usuario sigue en el servidor.',
            ],
            'discord_reactivation' => [
                'label' => 'Cambiar Discord RESERVA → ALPHA y actualizar apodo',
                'instructions' => 'Retira RESERVA/RECLUTA, asigna ALPHA y aplica al apodo la etiqueta ALPHA configurada.',
            ],
            'discord_reserve' => [
                'label' => 'Cambiar Discord ALPHA → RESERVA y actualizar apodo',
                'instructions' => 'Retira ALPHA/RECLUTA, asigna RESERVA y normaliza el apodo al nick de NewSlot sin la etiqueta ALPHA.',
            ],
            'discord_departure' => [
                'label' => 'Retirar roles/acceso de Discord',
                'instructions' => 'Retira los roles gestionados por NewSlot y normaliza el apodo si el usuario permanece en el servidor. En un CESE con baneo solicitado, lo banea automáticamente.',
            ],
        ];

        if (Schema::hasTable('member_procedure_step_definitions')) {
            foreach ($definitions as $stepKey => $definition) {
                DB::table('member_procedure_step_definitions')
                    ->where('step_key', $stepKey)
                    ->update([
                        'label' => $definition['label'],
                        'instructions' => $definition['instructions'],
                        'updated_at' => now(),
                    ]);
            }
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('member_procedure_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('member_procedure_settings', 'discord_invite_channel_id') ? 'discord_invite_channel_id' : null,
                Schema::hasColumn('member_procedure_settings', 'discord_bot_nickname') ? 'discord_bot_nickname' : null,
                Schema::hasColumn('member_procedure_settings', 'discord_alpha_nickname_prefix') ? 'discord_alpha_nickname_prefix' : null,
            ]));

            if ($columns !== []) {
                Schema::table('member_procedure_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }

        if (Schema::hasTable('homepage_settings')) {
            $columns = array_values(array_filter([
                Schema::hasColumn('homepage_settings', 'x_url') ? 'x_url' : null,
                Schema::hasColumn('homepage_settings', 'youtube_url') ? 'youtube_url' : null,
                Schema::hasColumn('homepage_settings', 'discord_invite_url') ? 'discord_invite_url' : null,
                Schema::hasColumn('homepage_settings', 'discord_invite_auto_refresh') ? 'discord_invite_auto_refresh' : null,
                Schema::hasColumn('homepage_settings', 'discord_invite_refreshed_at') ? 'discord_invite_refreshed_at' : null,
                Schema::hasColumn('homepage_settings', 'discord_invite_expires_at') ? 'discord_invite_expires_at' : null,
            ]));

            if ($columns !== []) {
                Schema::table('homepage_settings', function (Blueprint $table) use ($columns): void {
                    $table->dropColumn($columns);
                });
            }
        }
    }
};
