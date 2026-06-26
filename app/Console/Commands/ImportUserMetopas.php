<?php

namespace App\Console\Commands;

use App\Models\Metopa;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;

class ImportUserMetopas extends Command
{
    protected $signature = 'metopas:users {file}';

    protected $description = 'Importa las metopas de los usuarios desde un JSON';

    public function handle(): int
    {
        $file = base_path($this->argument('file'));

        if (! File::exists($file)) {
            $this->error("No existe el archivo: {$file}");
            return self::FAILURE;
        }

        $json = json_decode(File::get($file), true);

        if (! is_array($json)) {
            $this->error('El JSON no es válido.');
            return self::FAILURE;
        }

        foreach ($json as $registro) {

            $user = User::where('nick', $registro['nombre'])->first();

            if (! $user) {
                $this->warn("Usuario no encontrado: {$registro['nombre']}");
                continue;
            }

            $metopas = [];

            foreach ($registro['metopas'] as $nombreMetopa) {

                $metopa = Metopa::where('name', $nombreMetopa)->first();

                if (! $metopa) {
                    $this->warn("Metopa no encontrada: {$nombreMetopa}");
                    continue;
                }

                $metopas[] = $metopa->id;
            }

            $user->metopas()->sync($metopas);

            $this->info("✔ {$user->nick} (" . count($metopas) . " metopas)");
        }

        $this->newLine();
        $this->info('Importación terminada correctamente.');

        return self::SUCCESS;
    }
}