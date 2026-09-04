<?php

namespace App\Console\Commands;

use App\Models\HomepageSetting;
use App\Services\HomepageGooglePhotosService;
use Illuminate\Console\Command;

class RefreshHomepageGooglePhotos extends Command
{
    protected $signature = 'homepage:refresh-google-photos {--limit=6 : Número de fotos a conservar}';

    protected $description = 'Actualiza en caché las fotos de Google Fotos usadas en la portada sin bloquear peticiones web.';

    public function handle(HomepageGooglePhotosService $service): int
    {
        $limit = max(1, min(12, (int) $this->option('limit')));
        $settings = HomepageSetting::current();
        $albumUrl = trim((string) (
            $settings->google_photos_url
            ?: config('services.google_photos.album_url')
        ));

        if ($albumUrl === '') {
            $this->warn('No hay una URL de Google Fotos configurada para la portada.');

            return self::SUCCESS;
        }

        $photos = $service->refresh($limit, $albumUrl);

        if ($photos->isEmpty()) {
            $this->warn('No se han podido obtener fotos. La portada seguirá cargando sin esperar a Google Fotos.');

            return self::SUCCESS;
        }

        $this->info("Fotos disponibles en caché para la portada: {$photos->count()}");

        return self::SUCCESS;
    }
}
