<?php

namespace App\Console\Commands;

use App\Models\Metopa;
use App\Models\User;
use App\Services\UserMetopaAssignmentService;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;

class ImportUserMetopas extends Command
{
    protected $signature =
        'metopas:users
        {file : Ruta del JSON}
        {--sync : Sincroniza exactamente las metopas del JSON}';


    protected $description =
        'Importa las metopas de los usuarios desde un JSON';


    public function handle(): int
    {
        /*
        |--------------------------------------------------------------------------
        | Archivo
        |--------------------------------------------------------------------------
        */

        $file =
            base_path(
                $this->argument('file')
            );

        $sync =
            (bool) $this->option(
                'sync'
            );


        if (! File::exists($file)) {
            $this->error(
                "No existe el archivo: {$file}"
            );

            return self::FAILURE;
        }


        $json =
            json_decode(
                File::get($file),
                true
            );


        if (! is_array($json)) {
            $this->error(
                'El JSON no es válido.'
            );

            return self::FAILURE;
        }


        /*
        |--------------------------------------------------------------------------
        | Servicio único para modificar metopa_user
        |--------------------------------------------------------------------------
        |
        | Desde aquí NO utilizamos:
        |
        | sync()
        | attach()
        | detach()
        | syncWithoutDetaching()
        |
        | Todos los cambios pasan por este servicio
        | para que puedan auditarse.
        |
        */

        $service =
            app(
                UserMetopaAssignmentService::class
            );


        /*
        |--------------------------------------------------------------------------
        | Usuarios del JSON
        |--------------------------------------------------------------------------
        */

        foreach ($json as $registro) {
            $nick =
                $registro['nombre']
                ?? null;


            $user =
                User::query()
                    ->where(
                        'nick',
                        $nick
                    )
                    ->first();


            if ($user === null) {
                $this->warn(
                    'Usuario no encontrado: '
                    . (
                        $nick
                        ?? 'SIN NOMBRE'
                    )
                );

                continue;
            }


            /*
            |--------------------------------------------------------------------------
            | Construir lista deseada
            |--------------------------------------------------------------------------
            */

            $syncData = [];

            $lastAssignedAt =
                now();


            foreach (
                $registro['metopas']
                ?? []
                as $item
            ) {
                $nombreMetopa =
                    $item[0]
                    ?? null;

                $fecha =
                    $item[1]
                    ?? null;


                if (blank($nombreMetopa)) {
                    continue;
                }


                $metopa =
                    Metopa::query()
                        ->where(
                            'name',
                            mb_strtoupper(
                                $nombreMetopa
                            )
                        )
                        ->first();


                if ($metopa === null) {
                    $this->warn(
                        "Metopa no encontrada para {$user->nick}: {$nombreMetopa}"
                    );

                    continue;
                }


                /*
                |--------------------------------------------------------------------------
                | Fecha
                |--------------------------------------------------------------------------
                */

                if (! empty($fecha)) {
                    $assignedAt =
                        Carbon::createFromFormat(
                            'd/m/Y H:i:s',
                            $fecha
                        );
                } else {
                    /*
                     * Si no hay fecha, mantenemos
                     * el comportamiento que ya tenías:
                     *
                     * cada nueva metopa se separa
                     * un segundo de la anterior.
                     */
                    $assignedAt =
                        $lastAssignedAt
                            ->copy()
                            ->addSecond();
                }


                $lastAssignedAt =
                    $assignedAt->copy();


                /*
                 * Guardamos por ID para evitar
                 * metopas duplicadas.
                 */
                $syncData[
                    $metopa->id
                ] = [
                    'assigned_at' =>
                        $assignedAt
                            ->format(
                                'Y-m-d H:i:s'
                            ),
                ];
            }


            /*
            |--------------------------------------------------------------------------
            | Metopas que tenía ANTES
            |--------------------------------------------------------------------------
            |
            | Solo necesitamos esto en --sync.
            |
            */

            $currentMetopaIds = [];

            if ($sync) {
                $currentMetopaIds =
                    DB::table(
                        'metopa_user'
                    )
                        ->where(
                            'user_id',
                            $user->id
                        )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->pluck(
                            'metopa_id'
                        )
                        ->map(
                            fn ($id): int =>
                                (int) $id
                        )
                        ->all();
            }


            /*
            |--------------------------------------------------------------------------
            | Crear / restaurar / actualizar
            |--------------------------------------------------------------------------
            */

            foreach (
                $syncData
                as
                $metopaId =>
                $pivotData
            ) {
                $service->assign(
                    userId:
                        $user->id,

                    metopaId:
                        (int) $metopaId,

                    assignedAt:
                        $pivotData[
                            'assigned_at'
                        ],

                    /*
                     * El JSON es nuestra fuente
                     * de datos para la importación,
                     * por lo que actualizamos también
                     * assigned_at si ya existía.
                     */
                    updateExisting:
                        true,
                );
            }


            /*
            |--------------------------------------------------------------------------
            | Modo SYNC
            |--------------------------------------------------------------------------
            |
            | En --sync hay que eliminar las metopas
            | que tenía antes pero que ya NO aparecen
            | en el JSON.
            |
            */

            if ($sync) {
                $desiredMetopaIds =
                    array_map(
                        'intval',
                        array_keys(
                            $syncData
                        )
                    );


                $metopaIdsToDelete =
                    array_values(
                        array_diff(
                            $currentMetopaIds,
                            $desiredMetopaIds
                        )
                    );


                foreach (
                    $metopaIdsToDelete
                    as $metopaId
                ) {
                    $service->delete(
                        userId:
                            $user->id,

                        metopaId:
                            (int) $metopaId,
                    );
                }
            }


            /*
            |--------------------------------------------------------------------------
            | Resultado
            |--------------------------------------------------------------------------
            */

            $this->info(
                "✔ {$user->nick} ("
                . count($syncData)
                . ' metopas procesadas)'
            );
        }


        $this->newLine();


        $this->info(
            $sync
                ? 'Importación terminada correctamente en modo SYNC.'
                : 'Importación terminada correctamente sin eliminar metopas existentes.'
        );


        return self::SUCCESS;
    }
}