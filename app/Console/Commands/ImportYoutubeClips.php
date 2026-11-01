<?php

namespace App\Console\Commands;

use App\Services\YoutubeClipImportService;
use Illuminate\Console\Command;

class ImportYoutubeClips extends Command
{
    protected $signature = 'clips:import-youtube';
    protected $description = 'Incorpora vídeos cortos públicos de los canales de YouTube autorizados';

    public function handle(YoutubeClipImportService $import): int
    {
        $result = $import->run();
        if (($result['created'] ?? 0) > 0 || ($result['errors'] ?? 0) > 0) {
            app(\App\Services\AuditLogger::class)->automatic(
                'importacion_clips',
                ['platform' => 'youtube', 'created' => (int) $result['created'], 'skipped' => (int) $result['skipped'], 'errors' => (int) $result['errors']],
                process: 'clips:import-youtube',
            );
        }
        $this->table(['Streamers', 'Nuevos', 'Omitidos', 'Errores'], [[...array_values($result)]]);
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
