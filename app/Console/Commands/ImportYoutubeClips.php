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
        $this->table(['Streamers', 'Nuevos', 'Omitidos', 'Errores'], [[...array_values($result)]]);
        return $result['errors'] ? self::FAILURE : self::SUCCESS;
    }
}
