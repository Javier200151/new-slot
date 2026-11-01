<?php

namespace App\Filament\Resources\Streamers\Pages;

use App\Filament\Resources\Streamers\StreamerResource;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Notifications\Notification;
use App\Services\TwitchClipImportService;
use App\Services\YoutubeClipImportService;
use Throwable;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditStreamer extends EditRecord
{
    protected static string $resource = StreamerResource::class;

    protected function getHeaderActions(): array
    {
        return [
            Action::make('importClipsNow')
                ->label('Importar clips ahora')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->requiresConfirmation()
                ->modalHeading('Importar clips de este streamer')
                ->modalDescription('Consulta los clips de Twitch y YouTube dentro del período guardado para este streamer.')
                ->action(function (): void {
                    $streamer = $this->record->fresh();
                    if (! $streamer->enable || (! $streamer->auto_import_clips && ! $streamer->auto_import_youtube_clips)) {
                        Notification::make()->warning()->title('Activa la importación de Twitch o YouTube y guarda los cambios.')->send();
                        return;
                    }
                    try {
                        $result = ['created'=>0, 'skipped'=>0, 'errors'=>0];
                        if ($streamer->auto_import_clips) {
                            $data = app(TwitchClipImportService::class)->runFor($streamer);
                            foreach (['created','skipped','errors'] as $key) $result[$key] += $data[$key];
                        }
                        if ($streamer->auto_import_youtube_clips) {
                            $data = app(YoutubeClipImportService::class)->runFor($streamer);
                            foreach (['created','skipped','errors'] as $key) $result[$key] += $data[$key];
                        }
                        $alert = Notification::make()->title($result['errors'] ? 'Importación con incidencias' : 'Importación terminada')
                            ->body('Nuevos: ' . $result['created'] . ' · Omitidos: ' . $result['skipped'] . ' · Errores: ' . $result['errors']);
                        $result['errors'] ? $alert->warning()->send() : $alert->success()->send();
                    } catch (Throwable $exception) {
                        report($exception);
                        Notification::make()->danger()->title('No se pudieron importar los clips')->body($exception->getMessage())->send();
                    }
                }),
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
