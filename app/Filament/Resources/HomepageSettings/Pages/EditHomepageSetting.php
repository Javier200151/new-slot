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
                ->label('Regenerar invitación Discord')
                ->icon('heroicon-o-arrow-path-rounded-square')
                ->color('info')
                ->requiresConfirmation()
                ->modalHeading('Regenerar invitación pública de Discord')
                ->modalDescription('Se creará una invitación nueva de 7 días en el canal configurado y se sustituirá inmediatamente el enlace del pie de página.')
                ->modalSubmitActionLabel('Generar nueva invitación')
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
                            ->title('Invitación de Discord renovada')
                            ->body(($result['url'] ?? 'Invitación creada') . ' · caduca en 7 días.')
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
}
