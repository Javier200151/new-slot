<?php

namespace App\Console\Commands;

use App\Models\Metopa;
use App\Models\User;
use Carbon\Carbon;
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

            $syncData = [];
            $lastAssignedAt = now();

            foreach ($registro['metopas'] as $item) {
                $nombreMetopa = $item[0] ?? null;
                $fecha = $item[1] ?? null;

                if (! $nombreMetopa) {
                    continue;
                }

                $metopa = Metopa::where('name', mb_strtoupper($nombreMetopa))->first();

                if (! $metopa) {
                    $this->warn("Metopa no encontrada: {$nombreMetopa}");
                    continue;
                }

                if (! empty($fecha)) {
                    $assignedAt = Carbon::createFromFormat('d/m/Y H:i:s', $fecha);
                } else {
                    $assignedAt = $lastAssignedAt->copy()->addSecond();
                }

                $lastAssignedAt = $assignedAt->copy();

                $syncData[$metopa->id] = [
                    'assigned_at' => $assignedAt->format('Y-m-d H:i:s'),
                ];
            }

            $user->metopas()->sync($syncData);

            $this->info("✔ {$user->nick} (" . count($syncData) . " metopas)");
        }

        $this->newLine();
        $this->info('Importación terminada correctamente.');

        return self::SUCCESS;
    }
}