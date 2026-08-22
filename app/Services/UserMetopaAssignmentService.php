<?php

namespace App\Services;

use App\Models\Metopa;
use App\Models\User;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class UserMetopaAssignmentService
{
    /**
     * Asigna una metopa a un usuario.
     *
     * Puede devolver:
     *
     * created
     * restored
     * updated
     * already_exists
     */
    public function assign(
        int $userId,
        int $metopaId,
        CarbonInterface|string $assignedAt,
        bool $updateExisting = false,
    ): string {
        $this->validateIds(
            $userId,
            $metopaId,
        );

        $assignedAt =
            $this->normalizeAssignedAt(
                $assignedAt
            );

        $result = DB::transaction(
            function () use (
                $userId,
                $metopaId,
                $assignedAt,
                $updateExisting,
            ): string {
                /*
                |--------------------------------------------------------------------------
                | Usuario afectado
                |--------------------------------------------------------------------------
                */

                $user = User::query()
                    ->findOrFail($userId);


                /*
                |--------------------------------------------------------------------------
                | Buscar la asignación
                |--------------------------------------------------------------------------
                |
                | Importante:
                |
                | NO filtramos deleted_at porque necesitamos saber también
                | si existe una asignación eliminada que haya que restaurar.
                |
                */

                $assignment =
                    $this->findAssignment(
                        $userId,
                        $metopaId,
                    );


                /*
                |--------------------------------------------------------------------------
                | CREAR
                |--------------------------------------------------------------------------
                */

                if ($assignment === null) {
                    $now = now();

                    DB::table('metopa_user')
                        ->insert([
                            'user_id' =>
                                $userId,

                            'metopa_id' =>
                                $metopaId,

                            'assigned_at' =>
                                $assignedAt,

                            'created_by' =>
                                Auth::id(),

                            'updated_by' =>
                                Auth::id(),

                            'created_at' =>
                                $now,

                            'updated_at' =>
                                $now,

                            'deleted_at' =>
                                null,
                        ]);


                    /*
                     * Ahora que YA existe,
                     * volvemos a leerla.
                     */
                    $newAssignment =
                        $this->findAssignment(
                            $userId,
                            $metopaId,
                        );


                    /*
                     * Auditoría.
                     *
                     * ANTES = nada.
                     * DESPUÉS = fila creada.
                     */
                    $this->auditAssignment(
                        user: $user,

                        event:
                            'metopa_assignment_created',

                        userId:
                            $userId,

                        metopaId:
                            $metopaId,

                        old: [],

                        new:
                            (array) $newAssignment,
                    );


                    return 'created';
                }


                /*
                |--------------------------------------------------------------------------
                | RESTAURAR
                |--------------------------------------------------------------------------
                |
                | La fila existe pero tiene deleted_at.
                |
                */

                if (
                    $assignment->deleted_at
                    !== null
                ) {
                    /*
                     * Copia exacta ANTES del cambio.
                     */
                    $oldAssignment =
                        (array) $assignment;

                    $now = now();


                    DB::table('metopa_user')
                        ->where(
                            'user_id',
                            $userId
                        )
                        ->where(
                            'metopa_id',
                            $metopaId
                        )
                        ->update([
                            'assigned_at' =>
                                $assignedAt,

                            'deleted_at' =>
                                null,

                            'updated_by' =>
                                Auth::id(),

                            'updated_at' =>
                                $now,
                        ]);


                    /*
                     * Fila DESPUÉS del cambio.
                     */
                    $newAssignment =
                        $this->findAssignment(
                            $userId,
                            $metopaId,
                        );


                    $this->auditAssignment(
                        user: $user,

                        event:
                            'metopa_assignment_restored',

                        userId:
                            $userId,

                        metopaId:
                            $metopaId,

                        old:
                            $oldAssignment,

                        new:
                            (array) $newAssignment,
                    );


                    return 'restored';
                }


                /*
                |--------------------------------------------------------------------------
                | YA EXISTE
                |--------------------------------------------------------------------------
                */

                if (! $updateExisting) {
                    return 'already_exists';
                }


                /*
                |--------------------------------------------------------------------------
                | ACTUALIZAR
                |--------------------------------------------------------------------------
                */

                $oldAssignment =
                    (array) $assignment;

                $now = now();


                DB::table('metopa_user')
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'metopa_id',
                        $metopaId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->update([
                        'assigned_at' =>
                            $assignedAt,

                        'updated_by' =>
                            Auth::id(),

                        'updated_at' =>
                            $now,
                    ]);


                $newAssignment =
                    $this->findAssignment(
                        $userId,
                        $metopaId,
                    );


                $this->auditAssignment(
                    user: $user,

                    event:
                        'metopa_assignment_updated',

                    userId:
                        $userId,

                    metopaId:
                        $metopaId,

                    old:
                        $oldAssignment,

                    new:
                        (array) $newAssignment,
                );


                return 'updated';
            }
        );


        /*
        |--------------------------------------------------------------------------
        | Notificación comunitaria
        |--------------------------------------------------------------------------
        |
        | Solo notificamos cuando:
        |
        | - se concede por primera vez
        | - se restaura una concesión eliminada
        |
        | NO cuando simplemente cambiamos assigned_at.
        |
        */

        if (
            in_array(
                $result,
                [
                    'created',
                    'restored',
                ],
                true
            )
        ) {
            $user =
                User::query()
                    ->find($userId);

            $metopa =
                Metopa::query()
                    ->find($metopaId);


            if (
                $user !== null
                && $metopa !== null
            ) {
                app(
                    CommunityNotificationService::class
                )->metopaAwarded(
                    $user,
                    $metopa,
                );
            }
        }


        return $result;
    }


    /**
     * Modifica únicamente la fecha de asignación.
     */
    public function updateAssignedAt(
        int $userId,
        int $metopaId,
        CarbonInterface|string $assignedAt,
    ): void {
        $this->validateIds(
            $userId,
            $metopaId,
        );

        $assignedAt =
            $this->normalizeAssignedAt(
                $assignedAt
            );


        DB::transaction(
            function () use (
                $userId,
                $metopaId,
                $assignedAt,
            ): void {
                /*
                 * Buscamos únicamente una asignación activa.
                 */
                $assignment =
                    DB::table('metopa_user')
                        ->where(
                            'user_id',
                            $userId
                        )
                        ->where(
                            'metopa_id',
                            $metopaId
                        )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->first();


                if ($assignment === null) {
                    throw ValidationException::withMessages([
                        'assigned_at' =>
                            'La asignación ya no existe o fue eliminada.',
                    ]);
                }


                /*
                 * Estado ANTES.
                 */
                $oldAssignment =
                    (array) $assignment;


                DB::table('metopa_user')
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'metopa_id',
                        $metopaId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->update([
                        'assigned_at' =>
                            $assignedAt,

                        'updated_by' =>
                            Auth::id(),

                        'updated_at' =>
                            now(),
                    ]);


                /*
                 * Estado DESPUÉS.
                 */
                $newAssignment =
                    $this->findAssignment(
                        $userId,
                        $metopaId,
                    );


                $user =
                    User::query()
                        ->findOrFail(
                            $userId
                        );


                $this->auditAssignment(
                    user: $user,

                    event:
                        'metopa_assignment_updated',

                    userId:
                        $userId,

                    metopaId:
                        $metopaId,

                    old:
                        $oldAssignment,

                    new:
                        (array) $newAssignment,
                );
            }
        );
    }


    /**
     * Eliminación lógica de una asignación.
     */
    public function delete(
        int $userId,
        int $metopaId,
    ): void {
        $this->validateIds(
            $userId,
            $metopaId,
        );


        DB::transaction(
            function () use (
                $userId,
                $metopaId,
            ): void {
                /*
                 * Solo podemos eliminar una
                 * asignación actualmente activa.
                 */
                $assignment =
                    DB::table('metopa_user')
                        ->where(
                            'user_id',
                            $userId
                        )
                        ->where(
                            'metopa_id',
                            $metopaId
                        )
                        ->whereNull(
                            'deleted_at'
                        )
                        ->first();


                if ($assignment === null) {
                    throw ValidationException::withMessages([
                        'assignment' =>
                            'La asignación ya no existe o ya fue eliminada.',
                    ]);
                }


                /*
                 * Estado completo ANTES.
                 */
                $oldAssignment =
                    (array) $assignment;


                DB::table('metopa_user')
                    ->where(
                        'user_id',
                        $userId
                    )
                    ->where(
                        'metopa_id',
                        $metopaId
                    )
                    ->whereNull(
                        'deleted_at'
                    )
                    ->update([
                        'deleted_at' =>
                            now(),

                        'updated_by' =>
                            Auth::id(),

                        'updated_at' =>
                            now(),
                    ]);


                /*
                 * Como usamos soft-delete,
                 * la fila todavía existe.
                 *
                 * Podemos consultar cómo quedó.
                 */
                $newAssignment =
                    $this->findAssignment(
                        $userId,
                        $metopaId,
                    );


                $user =
                    User::query()
                        ->findOrFail(
                            $userId
                        );


                $this->auditAssignment(
                    user: $user,

                    event:
                        'metopa_assignment_deleted',

                    userId:
                        $userId,

                    metopaId:
                        $metopaId,

                    old:
                        $oldAssignment,

                    new:
                        (array) $newAssignment,
                );
            }
        );
    }


    /**
     * Busca una asignación independientemente
     * de que esté activa o eliminada.
     */
    private function findAssignment(
        int $userId,
        int $metopaId,
    ): ?object {
        return DB::table('metopa_user')
            ->where(
                'user_id',
                $userId
            )
            ->where(
                'metopa_id',
                $metopaId
            )
            ->first();
    }


    /**
     * Punto único de auditoría para metopa_user.
     */
    private function auditAssignment(
        User $user,
        string $event,
        int $userId,
        int $metopaId,
        array $old,
        array $new,
    ): void {
        app(AuditLogger::class)
            ->change(
                subject:
                    $user,

                event:
                    $event,

                old:
                    $old,

                new:
                    $new,

                properties: [
                    /*
                     * Como el subject real del Activity
                     * es User, indicamos expresamente
                     * que la fila modificada pertenece
                     * a metopa_user.
                     */
                    'table' =>
                        'metopa_user',

                    /*
                     * Clave primaria lógica.
                     *
                     * Esta tabla no tiene id:
                     * usa user_id + metopa_id.
                     */
                    'record_key' => [
                        'user_id' =>
                            $userId,

                        'metopa_id' =>
                            $metopaId,
                    ],

                    /*
                     * Información útil para investigar
                     * el registro aunque posteriormente
                     * cambien nombres.
                     */
                    'affected_user' => [
                        'id' =>
                            $user->id,

                        'nick' =>
                            $user->nick,
                    ],
                ],
            );
    }


    /**
     * Normaliza fecha para que DB y auditoría
     * utilicen siempre el mismo formato.
     */
    private function normalizeAssignedAt(
        CarbonInterface|string $assignedAt,
    ): string {
        if (
            $assignedAt
            instanceof CarbonInterface
        ) {
            return $assignedAt
                ->format(
                    'Y-m-d H:i:s'
                );
        }


        return Carbon::parse(
            $assignedAt
        )->format(
            'Y-m-d H:i:s'
        );
    }


    /**
     * Comprueba que usuario y metopa existen.
     */
    private function validateIds(
        int $userId,
        int $metopaId,
    ): void {
        Validator::make(
            [
                'user_id' =>
                    $userId,

                'metopa_id' =>
                    $metopaId,
            ],
            [
                'user_id' => [
                    'required',
                    'integer',
                    'exists:users,id',
                ],

                'metopa_id' => [
                    'required',
                    'integer',
                    'exists:metopas,id',
                ],
            ],
        )->validate();
    }
}