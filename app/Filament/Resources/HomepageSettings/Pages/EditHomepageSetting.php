<?php

namespace App\Filament\Resources\HomepageSettings\Pages;

use App\Filament\Resources\HomepageSettings\HomepageSettingResource;
use App\Services\HomepageGooglePhotosService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

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
        ];
    }
}
