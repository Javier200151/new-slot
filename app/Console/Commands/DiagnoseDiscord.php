<?php

namespace App\Console\Commands;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\DiscordService;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseDiscord extends Command
{
    protected $signature = 'discord:diagnose';

    protected $description = 'Comprueba la configuración, permisos, roles y canal de invitaciones del bot de Discord';

    public function handle(DiscordService $discord): int
    {
        try {
            $result = $discord->testConnection(MemberProcedureSetting::current());

            $this->info('Discord conectado correctamente.');
            $this->line('Bot: ' . ($result['bot_name'] ?? '-') . ' (' . ($result['bot_id'] ?? '-') . ')');
            $this->line('Servidor: ' . ($result['guild_name'] ?? '-') . ' (' . ($result['guild_id'] ?? '-') . ')');
            $this->line('Apodo del bot: ' . (($result['bot_nickname'] ?? null) ?: '(sin apodo)'));

            foreach ((array) ($result['roles'] ?? []) as $label => $role) {
                $this->line(sprintf(
                    'Rol %s: %s (%s), posición %s',
                    $label,
                    $role['name'] ?? '-',
                    $role['id'] ?? '-',
                    $role['position'] ?? '-',
                ));
            }

            if (is_array($result['invite_channel'] ?? null)) {
                $this->line('Canal de invitación: #' . ($result['invite_channel']['name'] ?? '-') . ' (' . ($result['invite_channel']['id'] ?? '-') . ')');
            } else {
                $this->warn('No hay Canal ID de invitación configurado.');
            }

            $this->table(
                ['Capacidad', 'Estado'],
                [
                    ['Gestionar roles', ($result['manage_roles'] ?? false) ? 'OK' : 'NO'],
                    ['Gestionar apodos', ($result['manage_nicknames'] ?? false) ? 'OK' : 'NO'],
                    ['Cambiar apodo del bot', ($result['change_nickname'] ?? false) ? 'OK' : 'NO'],
                    ['Crear invitaciones', ($result['create_invite'] ?? false) ? 'OK' : 'NO'],
                    ['Banear miembros', ($result['ban_members'] ?? false) ? 'OK' : 'NO'],
                ],
            );

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
