<?php

namespace App\Console\Commands;

use App\Models\Metopa;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportMetopas extends Command
{
    protected $signature = 'metopas:import';

    protected $description = 'Importa automáticamente las metopas desde storage/app/public/metopas';

    public function handle(): int
    {
        $path = storage_path('app/public/metopas');
        $largePath = storage_path('app/public/metopas_large');

        if (! File::exists($path)) {
            $this->error("No existe la carpeta: {$path}");
            return self::FAILURE;
        }

        $files = File::files($path);

        if (empty($files)) {
            $this->warn('No se encontraron metopas.');
            return self::SUCCESS;
        }

        foreach ($files as $file) {
            $filename = $file->getFilename();
            $largeImage = $largePath . DIRECTORY_SEPARATOR . $filename;

            $nombre = pathinfo($filename, PATHINFO_FILENAME);
            $nombre = preg_replace('/_ribbon$/i', '', $nombre);
            $nombre = str_replace('_', ' ', $nombre);
            $nombre = mb_strtoupper($nombre);

            if (! File::exists($largeImage)) {
                $this->warn("Falta la imagen grande: metopas_large/{$filename}");
            }

            Metopa::updateOrCreate(
                [
                    'image' => 'metopas/' . $filename,
                ],
                [
                    'name' => $nombre,
                    'image_large' => File::exists($largeImage)
                        ? 'metopas_large/' . $filename
                        : null,
                ]
            );

            $this->info("{$filename} → {$nombre}");
        }

        $this->newLine();
        $this->info('Importación terminada correctamente.');

        return self::SUCCESS;
    }
}