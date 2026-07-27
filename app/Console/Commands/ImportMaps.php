<?php

namespace App\Console\Commands;

use App\Models\Map;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use JsonException;
use RuntimeException;
use Throwable;

class ImportMaps extends Command
{
    /**
     * Dominios permitidos para descargar imágenes.
     */
    private const ALLOWED_IMAGE_HOSTS = [
        'atlas.plan-ops.fr',
    ];

    protected $signature = 'maps:import
        {file=storage/app/imports/maps.json : Ruta del archivo JSON}
        {--without-images : No descargar las imágenes}
        {--dry-run : Validar sin guardar cambios}';

    protected $description = 'Importa mapas desde un archivo JSON';

    public function handle(): int
    {
        $path = $this->resolvePath(
            (string) $this->argument('file')
        );

        $dryRun = (bool) $this->option('dry-run');
        $downloadImages = ! $dryRun
            && ! $this->option('without-images');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");

            return self::FAILURE;
        }

        try {
            $json = file_get_contents($path);

            if ($json === false) {
                throw new RuntimeException(
                    'No se pudo leer el archivo.'
                );
            }

            $payload = json_decode(
                $json,
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (JsonException $exception) {
            $this->error(
                'El JSON no es válido: '.$exception->getMessage()
            );

            return self::FAILURE;
        } catch (Throwable $exception) {
            $this->error(
                'No se pudo leer el archivo: '.$exception->getMessage()
            );

            return self::FAILURE;
        }

        /*
         * Permite estos dos formatos:
         *
         * [ {...}, {...} ]
         *
         * o:
         *
         * { "maps": [ {...}, {...} ] }
         */
        if (
            is_array($payload)
            && isset($payload['maps'])
            && is_array($payload['maps'])
        ) {
            $maps = $payload['maps'];
        } else {
            $maps = $payload;
        }

        if (! is_array($maps) || ! array_is_list($maps)) {
            $this->error(
                'El JSON debe contener una lista de mapas.'
            );

            return self::FAILURE;
        }

        if ($maps === []) {
            $this->error('El JSON no contiene mapas.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $failed = 0;
        $imageWarnings = 0;
        $messages = [];

        foreach ($maps as $index => $data) {
            $position = $index + 1;

            if (! is_array($data)) {
                $failed++;

                $messages[] = "Mapa {$position}: debe ser un objeto JSON.";

                continue;
            }

            $validator = Validator::make($data, [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'platform_id' => [
                    'required',
                    'integer',
                    'exists:platforms,id',
                ],
                'description' => [
                    'nullable',
                    'string',
                    'max:255',
                ],
                'url' => [
                    'nullable',
                    'url',
                    'max:255',
                ],
                'image' => [
                    'nullable',
                    'url',
                    'max:2048',
                ],
            ]);

            if ($validator->fails()) {
                $failed++;

                $messages[] = sprintf(
                    'Mapa %d: %s',
                    $position,
                    implode(' ', $validator->errors()->all())
                );

                continue;
            }

            $validated = $validator->validated();

            try {
                /*
                 * Un mapa se identifica por su nombre y plataforma.
                 * Si ya existe, se actualiza.
                 */
                $map = Map::firstOrNew([
                    'name' => $validated['name'],
                    'platform_id' => (int) $validated['platform_id'],
                ]);

                $isNew = ! $map->exists;
                $oldImage = $map->image;
                $newImage = $oldImage;

                $remoteImage = $validated['image'] ?? null;

                if ($downloadImages && filled($remoteImage)) {
                    try {
                        $newImage = $this->downloadImage(
                            url: $remoteImage,
                            mapName: $validated['name'],
                        );
                    } catch (Throwable $exception) {
                        /*
                         * El mapa se importa aunque falle la imagen.
                         * Si ya tenía una imagen, se conserva.
                         */
                        $imageWarnings++;

                        $messages[] = sprintf(
                            'Mapa %d, imagen de %s: %s',
                            $position,
                            $validated['name'],
                            $exception->getMessage()
                        );
                    }
                }

                $map->name = $validated['name'];
                $map->platform_id = (int) $validated['platform_id'];
                $map->description = $this->nullableString(
                    $validated['description'] ?? null
                );
                $map->url = $this->nullableString(
                    $validated['url'] ?? null
                );
                $map->image = $newImage;

                if (! $dryRun) {
                    $map->save();

                    $this->deleteReplacedImage(
                        oldImage: $oldImage,
                        newImage: $newImage,
                    );
                }

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $exception) {
                $failed++;

                $messages[] = sprintf(
                    'Mapa %d, %s: %s',
                    $position,
                    $validated['name'],
                    $exception->getMessage()
                );
            }
        }

        if ($messages !== []) {
            $this->newLine();

            foreach (array_slice($messages, 0, 30) as $message) {
                $this->warn($message);
            }

            if (count($messages) > 30) {
                $this->warn(
                    sprintf(
                        'Hay %d avisos adicionales.',
                        count($messages) - 30
                    )
                );
            }
        }

        $this->newLine();

        $this->table(
            ['Resultado', 'Cantidad'],
            [
                [$dryRun ? 'Se crearían' : 'Creados', $created],
                [$dryRun ? 'Se actualizarían' : 'Actualizados', $updated],
                ['Errores', $failed],
                ['Avisos de imágenes', $imageWarnings],
            ]
        );

        if ($dryRun) {
            $this->info(
                'Validación terminada. No se guardaron cambios.'
            );
        } else {
            $this->info('Importación de mapas terminada.');
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        if (str_starts_with($file, DIRECTORY_SEPARATOR)) {
            return $file;
        }

        return base_path($file);
    }

    private function nullableString(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }

        $value = trim($value);

        return $value === '' ? null : $value;
    }

    private function downloadImage(
        string $url,
        string $mapName
    ): string {
        $host = strtolower(
            (string) parse_url($url, PHP_URL_HOST)
        );

        if ($host === '') {
            throw new RuntimeException(
                'La URL de la imagen no contiene un dominio válido.'
            );
        }

        if (! in_array($host, self::ALLOWED_IMAGE_HOSTS, true)) {
            throw new RuntimeException(
                "El dominio {$host} no está permitido."
            );
        }

        $response = Http::withHeaders([
            'User-Agent' => config('app.name').'/map-importer',
        ])
            ->accept('image/webp,image/jpeg,image/png')
            ->connectTimeout(5)
            ->timeout(20)
            ->retry(3, 500)
            ->get($url);

        $response->throw();

        $contents = $response->body();

        if ($contents === '') {
            throw new RuntimeException(
                'La imagen descargada está vacía.'
            );
        }

        if (strlen($contents) > 10 * 1024 * 1024) {
            throw new RuntimeException(
                'La imagen supera el límite de 10 MB.'
            );
        }

        $imageInformation = @getimagesizefromstring($contents);

        if ($imageInformation === false) {
            throw new RuntimeException(
                'La URL no devuelve una imagen válida.'
            );
        }

        $mimeType = $imageInformation['mime'] ?? null;

        $extension = match ($mimeType) {
            'image/webp' => 'webp',
            'image/jpeg' => 'jpg',
            'image/png' => 'png',
            default => throw new RuntimeException(
                "Formato no permitido: {$mimeType}"
            ),
        };

        $slug = Str::slug($mapName);

        if ($slug === '') {
            $slug = 'map';
        }

        /*
         * El hash evita conflictos y hace que la misma URL
         * genere siempre el mismo nombre.
         */
        $filename = sprintf(
            '%s-%s.%s',
            $slug,
            substr(sha1($url), 0, 12),
            $extension
        );

        $storagePath = 'maps/'.$filename;

        $saved = Storage::disk('public')->put(
            $storagePath,
            $contents
        );

        if (! $saved) {
            throw new RuntimeException(
                'No se pudo guardar la imagen.'
            );
        }

        return $storagePath;
    }

    private function deleteReplacedImage(
        ?string $oldImage,
        ?string $newImage
    ): void {
        if (
            blank($oldImage)
            || $oldImage === $newImage
            || str_starts_with($oldImage, 'http://')
            || str_starts_with($oldImage, 'https://')
        ) {
            return;
        }

        $disk = Storage::disk('public');

        if ($disk->exists($oldImage)) {
            $disk->delete($oldImage);
        }
    }
}