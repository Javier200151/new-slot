<?php

namespace App\Console\Commands;

use App\Models\Addon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use JsonException;
use RuntimeException;
use Throwable;

class ImportAddons extends Command
{
    protected $signature = 'addons:import
        {file=storage/app/imports/addons.json : Ruta del archivo JSON}
        {--dry-run : Validar y mostrar cambios sin guardar}';

    protected $description = 'Importa o actualiza addons desde un archivo JSON';

    public function handle(): int
    {
        $path = $this->resolvePath(
            (string) $this->argument('file')
        );

        $dryRun = (bool) $this->option('dry-run');

        if (! is_file($path)) {
            $this->error("No se encontró el archivo: {$path}");

            return self::FAILURE;
        }

        try {
            $contents = file_get_contents($path);

            if ($contents === false) {
                throw new RuntimeException(
                    'No se pudo leer el archivo.'
                );
            }

            $payload = json_decode(
                $contents,
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
         * Acepta:
         *
         * [
         *     {...},
         *     {...}
         * ]
         *
         * o:
         *
         * {
         *     "addons": [
         *         {...},
         *         {...}
         *     ]
         * }
         */
        if (
            is_array($payload)
            && isset($payload['addons'])
            && is_array($payload['addons'])
        ) {
            $addons = $payload['addons'];
        } else {
            $addons = $payload;
        }

        if (! is_array($addons) || ! array_is_list($addons)) {
            $this->error(
                'El JSON debe contener una lista de addons.'
            );

            return self::FAILURE;
        }

        if ($addons === []) {
            $this->error('El JSON no contiene addons.');

            return self::FAILURE;
        }

        $created = 0;
        $updated = 0;
        $unchanged = 0;
        $failed = 0;
        $messages = [];

        foreach ($addons as $index => $data) {
            $position = $index + 1;

            if (! is_array($data)) {
                $failed++;
                $messages[] = "Addon {$position}: debe ser un objeto JSON.";

                continue;
            }

            $validator = Validator::make($data, [
                'name' => [
                    'required',
                    'string',
                    'max:255',
                ],
                'description' => [
                    'nullable',
                    'string',
                ],
                'mandatory' => [
                    'required',
                    'boolean',
                ],
                'active' => [
                    'required',
                    'boolean',
                ],
            ]);

            if ($validator->fails()) {
                $failed++;

                $messages[] = sprintf(
                    'Addon %d: %s',
                    $position,
                    implode(' ', $validator->errors()->all())
                );

                continue;
            }

            $validated = $validator->validated();
            $name = trim($validated['name']);

            try {
                /*
                 * El nombre identifica al addon.
                 * Si ya existe, se actualiza en lugar de duplicarlo.
                 */
                $addon = Addon::firstOrNew([
                    'name' => $name,
                ]);

                $isNew = ! $addon->exists;

                $addon->fill([
                    'description' => $this->nullableString(
                        $validated['description'] ?? null
                    ),
                    'mandatory' => (bool) $validated['mandatory'],
                    'active' => (bool) $validated['active'],
                ]);

                $hasChanges = $isNew || $addon->isDirty();

                if (! $hasChanges) {
                    $unchanged++;

                    continue;
                }

                if (! $dryRun) {
                    $addon->save();
                }

                if ($isNew) {
                    $created++;
                } else {
                    $updated++;
                }
            } catch (Throwable $exception) {
                $failed++;

                $messages[] = sprintf(
                    'Addon %d, %s: %s',
                    $position,
                    $name,
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
                ['Sin cambios', $unchanged],
                ['Errores', $failed],
            ]
        );

        if ($dryRun) {
            $this->info(
                'Validación terminada. No se guardaron cambios.'
            );
        } else {
            $this->info('Importación de addons terminada.');
        }

        return $failed > 0
            ? self::FAILURE
            : self::SUCCESS;
    }

    private function resolvePath(string $file): string
    {
        /*
         * Rutas absolutas de Windows:
         * C:\directorio\archivo.json
         *
         * Rutas UNC:
         * \\servidor\directorio\archivo.json
         */
        $isWindowsAbsolute = preg_match(
            '/^(?:[A-Za-z]:[\\\\\/]|[\\\\\/]{2})/',
            $file
        ) === 1;

        if (
            $isWindowsAbsolute
            || str_starts_with($file, DIRECTORY_SEPARATOR)
        ) {
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
}