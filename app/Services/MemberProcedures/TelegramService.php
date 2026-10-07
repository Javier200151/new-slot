<?php

namespace App\Services\MemberProcedures;

use App\Models\MemberProcedureSetting;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class TelegramService
{
    public function isConfigured(): bool
    {
        return (bool) config('newslot.procedures.telegram.enabled')
            && filled(config('newslot.procedures.telegram.bot_token'));
    }

    /** @return array<string, mixed> */
    public function testConnection(MemberProcedureSetting $setting): array
    {
        $this->assertApiConfigured();

        $me = $this->call('getMe');
        $botId = (string) ($me['id'] ?? '');
        $botName = (string) ($me['username'] ?? $me['first_name'] ?? $botId);

        if ($botId === '') {
            throw new RuntimeException('Telegram no devolvió el ID del bot.');
        }

        $chats = [];
        foreach ([
            'network' => $setting->telegram_network_chat_id,
        ] as $key => $chatId) {
            $chatId = trim((string) $chatId);
            if ($chatId === '') {
                continue;
            }

            $chat = $this->call('getChat', ['chat_id' => $chatId]);
            $member = $this->call('getChatMember', [
                'chat_id' => $chatId,
                'user_id' => $botId,
            ]);

            $this->assertCanSendToChat($chat, $member);

            $chats[$key] = [
                'id' => (string) ($chat['id'] ?? $chatId),
                'title' => $this->chatLabel($chat),
                'type' => (string) ($chat['type'] ?? ''),
                'bot_status' => (string) ($member['status'] ?? ''),
            ];
        }

        return [
            'bot_id' => $botId,
            'bot_name' => $botName,
            'chats' => $chats,
        ];
    }

    /** @return array<string, string> */
    public function discoverChats(): array
    {
        $this->assertApiConfigured();

        $updates = $this->call('getUpdates', [
            'limit' => 100,
            'timeout' => 0,
            'allowed_updates' => json_encode([
                'message',
                'edited_message',
                'channel_post',
                'edited_channel_post',
                'my_chat_member',
                'chat_member',
            ], JSON_UNESCAPED_SLASHES),
        ]);

        $options = [];
        foreach ($updates as $update) {
            if (! is_array($update)) {
                continue;
            }

            $chat = $this->chatFromUpdate($update);
            if (! is_array($chat) || ! isset($chat['id'])) {
                continue;
            }

            $id = (string) $chat['id'];
            $options[$id] = $this->chatLabel($chat) . ' · ' . $id;
        }

        asort($options, SORT_NATURAL | SORT_FLAG_CASE);
        Cache::put($this->catalogCacheKey(), $options, now()->addMinutes(15));

        return $options;
    }

    /** @return array<string, string> */
    public function cachedChatOptions(): array
    {
        $options = Cache::get($this->catalogCacheKey(), []);

        return is_array($options) ? $options : [];
    }

    public function clearCatalogCache(): void
    {
        Cache::forget($this->catalogCacheKey());
    }

    /** @return array<string, mixed> */
    public function sendMessage(string $chatId, string $text, ?string $parseMode = null): array
    {
        $this->assertApiConfigured();

        $chatId = trim($chatId);
        $text = trim($text);
        if ($chatId === '') {
            throw new RuntimeException('Falta el destino de Telegram.');
        }
        if ($text === '') {
            throw new RuntimeException('El mensaje de Telegram está vacío.');
        }
        if (mb_strlen($text) > 4096) {
            throw new RuntimeException('El mensaje de Telegram supera el límite de 4096 caracteres. Reduce la plantilla o el contenido.');
        }

        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
            'disable_notification' => false,
        ];

        if (filled($parseMode)) {
            $payload['parse_mode'] = $parseMode;
        }

        $result = $this->call('sendMessage', $payload);

        return [
            'chat_id' => (string) ($result['chat']['id'] ?? $chatId),
            'chat_title' => is_array($result['chat'] ?? null) ? $this->chatLabel($result['chat']) : $chatId,
            'message_id' => (int) ($result['message_id'] ?? 0),
        ];
    }

    /** @return array<string, mixed>|array<int, mixed> */
    private function call(string $method, array $payload = []): array
    {
        try {
            $response = $this->client()->post('/' . $method, $payload);
        } catch (\Throwable) {
            // El token de Telegram forma parte de la URL de la Bot API. No
            // propagamos la excepción HTTP original para evitar que el token
            // pueda terminar escrito en logs mediante la URL de la petición.
            throw new RuntimeException('No se pudo conectar con Telegram Bot API.');
        }

        $json = $response->json();

        if (! $response->successful() || ! is_array($json) || ($json['ok'] ?? false) !== true) {
            throw new RuntimeException($this->errorMessage($response, 'Telegram rechazó la operación ' . $method));
        }

        $result = $json['result'] ?? null;
        if (! is_array($result)) {
            throw new RuntimeException('Telegram respondió correctamente, pero no devolvió un resultado válido para ' . $method . '.');
        }

        return $result;
    }

    /** @return array<string, mixed>|null */
    private function chatFromUpdate(array $update): ?array
    {
        foreach (['message', 'edited_message', 'channel_post', 'edited_channel_post'] as $key) {
            if (is_array($update[$key]['chat'] ?? null)) {
                return $update[$key]['chat'];
            }
        }

        foreach (['my_chat_member', 'chat_member'] as $key) {
            if (is_array($update[$key]['chat'] ?? null)) {
                return $update[$key]['chat'];
            }
        }

        return null;
    }

    private function chatLabel(array $chat): string
    {
        $title = trim((string) ($chat['title'] ?? ''));
        if ($title !== '') {
            return $title;
        }

        $username = trim((string) ($chat['username'] ?? ''));
        if ($username !== '') {
            return '@' . ltrim($username, '@');
        }

        $name = trim(implode(' ', array_filter([
            (string) ($chat['first_name'] ?? ''),
            (string) ($chat['last_name'] ?? ''),
        ])));

        return $name !== '' ? $name : 'Chat de Telegram';
    }

    private function assertCanSendToChat(array $chat, array $member): void
    {
        $status = (string) ($member['status'] ?? '');
        if (in_array($status, ['left', 'kicked'], true)) {
            throw new RuntimeException('El bot no pertenece al chat «' . $this->chatLabel($chat) . '».');
        }

        if ($status === 'restricted' && ($member['can_send_messages'] ?? false) !== true) {
            throw new RuntimeException('El bot no puede enviar mensajes en «' . $this->chatLabel($chat) . '».');
        }

        if (($chat['type'] ?? null) === 'channel'
            && $status !== 'creator'
            && ($member['can_post_messages'] ?? false) !== true) {
            throw new RuntimeException('El bot necesita permiso para publicar mensajes en el canal «' . $this->chatLabel($chat) . '».');
        }
    }

    private function assertApiConfigured(): void
    {
        if (! (bool) config('newslot.procedures.telegram.enabled')) {
            throw new RuntimeException('Telegram está desactivado. Configura TELEGRAM_ENABLED=true.');
        }
        if (blank(config('newslot.procedures.telegram.bot_token'))) {
            throw new RuntimeException('Falta TELEGRAM_BOT_TOKEN en el entorno del servidor.');
        }
    }

    private function catalogCacheKey(): string
    {
        return 'newslot:telegram:chats:' . sha1((string) config('newslot.procedures.telegram.bot_token'));
    }

    private function errorMessage(Response $response, string $prefix): string
    {
        $remote = $response->json();
        $detail = is_array($remote) ? trim((string) ($remote['description'] ?? '')) : '';

        return $prefix . ' (HTTP ' . $response->status() . ')' . ($detail !== '' ? ': ' . $detail : '') . '.';
    }

    private function client(): PendingRequest
    {
        $baseUrl = rtrim((string) config('newslot.procedures.telegram.base_url', 'https://api.telegram.org'), '/');
        $token = trim((string) config('newslot.procedures.telegram.bot_token'));

        return Http::baseUrl($baseUrl . '/bot' . $token)
            ->acceptJson()
            ->asJson()
            ->timeout((int) config('newslot.procedures.telegram.timeout', 10));
    }
}
