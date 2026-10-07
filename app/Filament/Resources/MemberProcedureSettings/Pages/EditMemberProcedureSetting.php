<?php

namespace App\Filament\Resources\MemberProcedureSettings\Pages;

use App\Filament\Resources\MemberProcedureSettings\MemberProcedureSettingResource;
use App\Models\MemberProcedureSetting;
use App\Services\MemberProcedures\DiscordService;
use App\Services\MemberProcedures\GoogleSheetsService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditMemberProcedureSetting extends EditRecord
{
    protected static string $resource = MemberProcedureSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshDiscordCatalog')
                ->label('Recargar listas Discord')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(function (DiscordService $discord): void {
                    try {
                        /** @var MemberProcedureSetting $setting */
                        $setting = $this->record->fresh();
                        $discord->clearCatalogCache($setting->discord_guild_id);

                        $guilds = $discord->guildOptions();
                        $roles = filled($setting->discord_guild_id)
                            ? $discord->roleOptions((string) $setting->discord_guild_id)
                            : [];
                        $channels = filled($setting->discord_guild_id)
                            ? $discord->channelOptions((string) $setting->discord_guild_id)
                            : [];

                        $this->fillForm();

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

            Action::make('testGoogleSheets')
                ->label('Probar Google Sheets')
                ->icon('heroicon-o-cloud-arrow-up')
                ->color('info')
                ->action(function (GoogleSheetsService $googleSheets): void {
                    try {
                        /** @var MemberProcedureSetting $setting */
                        $setting = $this->record->fresh();
                        $result = $googleSheets->testConnection($setting);

                        Notification::make()
                            ->success()
                            ->title('Google Sheets conectado')
                            ->body('Lectura y escritura verificadas en la pestaña ' . ($result['sheet'] ?? 'General') . '.')
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

            Action::make('testDiscord')
                ->label('Probar Discord')
                ->icon('heroicon-o-chat-bubble-left-right')
                ->color('info')
                ->action(function (DiscordService $discord): void {
                    try {
                        /** @var MemberProcedureSetting $setting */
                        $setting = $this->record->fresh();
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
                        /** @var MemberProcedureSetting $setting */
                        $setting = $this->record->fresh();
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
}
