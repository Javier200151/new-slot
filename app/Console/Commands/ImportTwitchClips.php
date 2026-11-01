<?php

namespace App\Console\Commands;

use App\Services\TwitchClipImportService;
use Illuminate\Console\Command;

class ImportTwitchClips extends Command
{
    protected $signature = 'clips:import-twitch';
    protected $description = 'Incorpora los clips recientes de los streamers autorizados';

    public function handle(TwitchClipImportService $import): int
    {
        $result = $import->run();
        if (($result['created'] ?? 0) > 0 || ($result['errors'] ?? 0) > 0) {
            app(\App\Services\AuditLogger::class)->automatic(
                'importacion_clips',
                ['platform' => 'twitch', 'created' => (int) $result['created'], 'skipped' => (int) $result['skipped'], 'errors' => (int) $result['errors']],
                process: 'clips:import-twitch',
            );
        }
        $this->table(['Streamers', 'Nuevos', 'Omitidos', 'Errores'], [[...array_values($result)]]);
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
