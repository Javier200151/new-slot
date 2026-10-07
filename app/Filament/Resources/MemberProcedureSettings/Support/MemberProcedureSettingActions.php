<?php

namespace App\Filament\Resources\MemberProcedureSettings\Support;

use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\DiscordService;
use App\Services\MemberProcedures\GoogleSheetsService;
use App\Services\MemberProcedures\TelegramService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Throwable;

class MemberProcedureSettingActions
{
    /** @return array<int, Action> */
    public static function discord(): array
    {
        return [
            Action::make('refreshDiscordCatalog')
                ->label('Recargar listas')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (DiscordService $discord): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $discord->clearCatalogCache($setting->discord_guild_id);

                        $guilds = $discord->guildOptions();
                        $roles = filled($setting->discord_guild_id)
                            ? $discord->roleOptions((string) $setting->discord_guild_id)
                            : [];
                        $channels = filled($setting->discord_guild_id)
                            ? $discord->channelOptions((string) $setting->discord_guild_id)
                            : [];

                        Notification::make()
                            ->success()
                            ->title('Listas de Discord actualizadas')
                            ->body(count($guilds) . ' servidor(es) · ' . count($roles) . ' rol(es) · ' . count($channels) . ' canal(es) disponibles.')
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudieron cargar las listas de Discord')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('testDiscord')
                ->label('Probar conexión')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->action(function (DiscordService $discord): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $result = $discord->testConnection($setting);

                        $status = [
                            'Roles: OK',
                            'Apodos: ' . (($result['manage_nicknames'] ?? false) ? 'OK' : 'NO'),
                            'Apodo bot: ' . (($result['change_nickname'] ?? false) ? 'OK' : 'NO'),
                            'Invitaciones: ' . (($result['create_invite'] ?? false) ? 'OK' : 'NO'),
                            'Baneos: ' . (($result['ban_members'] ?? false) ? 'OK' : 'NO'),
                        ];

                        Notification::make()
                            ->success()
                            ->title('Discord conectado')
                            ->body(($result['bot_name'] ?? 'Bot') . ' · ' . ($result['guild_name'] ?? 'Servidor') . '. ' . implode(' · ', $status))
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Discord no está listo')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('applyDiscordBotNickname')
                ->label('Aplicar apodo del bot')
                ->icon('heroicon-o-identification')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Aplicar apodo del bot en Discord')
                ->modalDescription('Se usará el valor guardado en «Apodo del bot en el servidor».')
                ->modalSubmitActionLabel('Aplicar')
                ->action(function (DiscordService $discord): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $result = $discord->applyBotNickname($setting);

                        Notification::make()
                            ->success()
                            ->title('Apodo del bot actualizado')
                            ->body('Nuevo apodo: ' . ($result['nickname'] ?? $setting->discord_bot_nickname))
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudo cambiar el apodo del bot')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /** @return array<int, Action> */
    public static function telegram(): array
    {
        return [
            Action::make('refreshTelegramChats')
                ->label('Detectar chats')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (TelegramService $telegram): void {
                    try {
                        $telegram->clearCatalogCache();
                        $chats = $telegram->discoverChats();

                        if ($chats === []) {
                            Notification::make()
                                ->warning()
                                ->title('No se detectaron chats recientes')
                                ->body('Añade el bot al grupo/canal o envíale un mensaje y vuelve a intentarlo. También puedes introducir el ID o @usuario manualmente.')
                                ->persistent()
                                ->send();

                            return;
                        }

                        Notification::make()
                            ->success()
                            ->title('Chats de Telegram detectados')
                            ->body(implode(' · ', array_values(array_slice($chats, 0, 8, true))))
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudieron detectar los chats de Telegram')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('testTelegram')
                ->label('Probar conexión')
                ->icon('heroicon-o-paper-airplane')
                ->color('info')
                ->action(function (TelegramService $telegram): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $result = $telegram->testConnection($setting);
                        $chatNames = collect($result['chats'] ?? [])
                            ->map(fn (array $chat): string => (string) ($chat['title'] ?? $chat['id'] ?? 'chat'))
                            ->values()
                            ->all();

                        Notification::make()
                            ->success()
                            ->title('Telegram conectado')
                            ->body(($result['bot_name'] ?? 'Bot') . ($chatNames !== [] ? ' · ' . implode(' · ', $chatNames) : ' · Sin destinos configurados todavía.'))
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Telegram no está listo')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),

            Action::make('sendTelegramTest')
                ->label('Enviar mensaje de prueba')
                ->icon('heroicon-o-paper-airplane')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Enviar mensaje de prueba a Telegram')
                ->modalDescription('Se enviará un mensaje corto al chat configurado de = ALPHA FORCE NETWORK =.')
                ->modalSubmitActionLabel('Enviar prueba')
                ->action(function (TelegramService $telegram): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $chatId = trim((string) $setting->telegram_network_chat_id);

                        if ($chatId === '') {
                            throw new \RuntimeException('Configura primero el chat de = ALPHA FORCE NETWORK = para el bot de Telegram.');
                        }

                        $result = $telegram->sendMessage(
                            $chatId,
                            '✅ NewSlot conectado correctamente con Telegram.',
                        );

                        Notification::make()
                            ->success()
                            ->title('Mensaje de prueba enviado')
                            ->body('Destino: ' . ($result['chat_title'] ?? $result['chat_id'] ?? $chatId))
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudo enviar el mensaje de prueba')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /** @return array<int, Action> */
    public static function googleSheets(): array
    {
        return [
            Action::make('testGoogleSheets')
                ->label('Probar conexión')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->action(function (GoogleSheetsService $googleSheets): void {
                    try {
                        $setting = MemberProcedureSetting::current();
                        $result = $googleSheets->testConnection($setting);

                        Notification::make()
                            ->success()
                            ->title('Google Sheets conectado')
                            ->body('Lectura y escritura verificadas en la pestaña ' . ($result['sheet'] ?? 'General') . '.')
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('Google Sheets no está listo')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }
}
