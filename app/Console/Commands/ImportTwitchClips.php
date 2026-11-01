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
        $this->table(['Streamers', 'Nuevos', 'Omitidos', 'Errores'], [[...array_values($result)]]);
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
