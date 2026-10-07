<?php

namespace App\Console\Commands;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\TelegramService;
use Illuminate\Console\Command;
use Throwable;

class DiagnoseTelegram extends Command
{
    protected $signature = 'telegram:diagnose {--discover : Muestra chats recientes detectables mediante getUpdates}';

    protected $description = 'Comprueba el bot de Telegram y el chat configurado de ALPHA Network';

    public function handle(TelegramService $telegram): int
    {
        try {
            $setting = MemberProcedureSetting::current();
            $result = $telegram->testConnection($setting);

            $this->info('Telegram conectado correctamente.');
            $this->line('Bot: @' . ltrim((string) ($result['bot_name'] ?? '-'), '@') . ' (' . ($result['bot_id'] ?? '-') . ')');

            $chats = (array) ($result['chats'] ?? []);
            if ($chats === []) {
                $this->warn('No hay destinos de Telegram configurados todavía.');
            } else {
                foreach ($chats as $key => $chat) {
                    $this->line(sprintf(
                        '%s: %s (%s) · %s',
                        $key === 'network' ? 'ALPHA Network' : $key,
                        $chat['title'] ?? '-',
                        $chat['id'] ?? '-',
                        $chat['bot_status'] ?? '-',
                    ));
                }
            }

            if ($this->option('discover')) {
                $options = $telegram->discoverChats();
                if ($options === []) {
                    $this->warn('No se encontraron chats recientes. Añade el bot a un chat o envíale un mensaje y vuelve a intentarlo.');
                } else {
                    $this->newLine();
                    $this->info('Chats recientes detectados:');
                    foreach ($options as $id => $label) {
                        $this->line($label);
                    }
                }
            }

            return self::SUCCESS;
        } catch (Throwable $exception) {
            report($exception);
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }
}
