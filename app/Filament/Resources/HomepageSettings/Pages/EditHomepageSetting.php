<?php

namespace App\Filament\Resources\HomepageSettings\Pages;

use App\Filament\Resources\HomepageSettings\HomepageSettingResource;
use App\Models\MemberProcedureSetting;
use App\Services\HomepageGooglePhotosService;
use App\Services\MemberProcedures\DiscordService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Throwable;

class EditHomepageSetting extends EditRecord
{
    protected static string $resource = HomepageSettingResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('refreshGooglePhotos')
                ->label('Actualizar Google Fotos')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Actualizar Google Fotos')
                ->modalDescription('Se consultará ahora el álbum público y, si responde correctamente, se sustituirá la caché usada en la portada.')
                ->modalSubmitActionLabel('Actualizar ahora')
                ->action(function (): void {
                    $albumUrl = trim((string) (
                        $this->record->google_photos_url
                        ?: config('services.google_photos.album_url')
                    ));

                    if ($albumUrl === '') {
                        Notification::make()
                            ->title('No hay álbum configurado')
                            ->body('Guarda primero el enlace público de Google Fotos.')
                            ->warning()
                            ->send();

                        return;
                    }

                    $startedAt = hrtime(true);
                    $result = app(HomepageGooglePhotosService::class)
                        ->refreshWithResult(6, $albumUrl);
                    $elapsedMs = (hrtime(true) - $startedAt) / 1_000_000;
                    $photos = $result['photos'];

                    if (! $result['refreshed']) {
                        Notification::make()
                            ->title('Google Fotos no respondió correctamente')
                            ->body(
                                $photos->isNotEmpty()
                                    ? 'No se ha cambiado la caché. La portada seguirá usando las ' . $photos->count() . ' fotos válidas anteriores.'
                                    : 'No se han obtenido fotos y tampoco existe una caché válida anterior.'
                            )
                            ->warning()
                            ->send();

                        return;
                    }

                    Notification::make()
                        ->title('Google Fotos actualizado')
                        ->body($photos->count() . ' fotos renovadas · ' . number_format($elapsedMs / 1000, 1) . ' s')
                        ->success()
                        ->send();
                }),

            Action::make('refreshDiscordInvite')
                ->label('Actualizar invitación Discord')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Actualizar invitación al servidor de Discord')
                ->modalDescription('Se obtendrá el enlace del servidor o se creará una invitación para entrar en él. El enlace actual se sustituirá.')
                ->modalSubmitActionLabel('Actualizar enlace')
                ->action(function (DiscordService $discord): void {
                    try {
                        $result = $discord->refreshPublicInvite(
                            MemberProcedureSetting::current(),
                            $this->record->fresh(),
                        );

                        $this->record->refresh();
                        $this->fillForm();

                        Notification::make()
                            ->success()
                            ->title('Invitación al servidor actualizada')
                            ->body(($result['url'] ?? 'Invitación creada') . ($result['expires_at'] ? ' · se renovará antes de caducar.' : ' · sin caducidad conocida.'))
                            ->persistent()
                            ->send();
                    } catch (Throwable $exception) {
                        report($exception);

                        Notification::make()
                            ->danger()
                            ->title('No se pudo renovar la invitación')
                            ->body($exception->getMessage())
                            ->persistent()
                            ->send();
                    }
                }),
        ];
    }

    /** Si se escribe otro enlace, respetarlo sin reemplazarlo al día siguiente. */
    protected function mutateFormDataBeforeSave(array $data): array
    {
        $incoming = trim((string) ($data['discord_invite_url'] ?? ''));
        $current = trim((string) $this->record->discord_invite_url);
        if ($incoming !== $current) {
            $data['discord_invite_url'] = $incoming !== '' ? $incoming : null;
            if ($incoming !== '') {
                $data['discord_invite_auto_refresh'] = false;
            }
            $data['discord_invite_refreshed_at'] = null;
            $data['discord_invite_expires_at'] = null;
        }

        return $data;
    }

    protected function afterSave(): void
    {
        $homepage = $this->record->fresh();
        $discord = app(DiscordService::class);
        if (! $discord->publicInviteNeedsRefresh($homepage)) {
            if ($this->record->wasChanged('discord_invite_url')) {
                $this->fillForm();
            }

            return;
        }

        try {
            $discord->refreshPublicInvite(MemberProcedureSetting::current(), $homepage);
            $this->record->refresh();
            $this->fillForm();

            Notification::make()
                ->success()
                ->title('Invitación al servidor actualizada')
                ->send();
        } catch (Throwable $exception) {
            report($exception);

            Notification::make()
                ->warning()
                ->title('No se pudo crear la invitación')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
        }
    }

}
