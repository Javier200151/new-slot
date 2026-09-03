<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GUARD = 'web';
    private const OLD_PERMISSION = 'activities.view';
    private const NEW_PERMISSION = 'audit-log.view';

    public function up(): void
    {
        $this->renamePermission(
            self::OLD_PERMISSION,
            self::NEW_PERMISSION,
        );
    }

    public function down(): void
    {
        $this->renamePermission(
            self::NEW_PERMISSION,
            self::OLD_PERMISSION,
        );
    }

    private function renamePermission(string $from, string $to): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        $source = DB::table('permissions')
            ->where('name', $from)
            ->where('guard_name', self::GUARD)
            ->first(['id']);

        $target = DB::table('permissions')
            ->where('name', $to)
            ->where('guard_name', self::GUARD)
            ->first(['id']);

        /*
         * Estado ya migrado (o instalación nueva en la que solo existe
         * el nombre nuevo): no hay nada que hacer.
         */
        if ($source === null && $target !== null) {
            return;
        }

        /*
         * Una instalación que todavía no tenga ninguno de los dos permisos
         * puede dejar que PermissionsSeeder cree el nombre canónico después.
         */
        if ($source === null && $target === null) {
            return;
        }

        /*
         * Nunca fusionamos o eliminamos permisos automáticamente. Si por una
         * divergencia entre entornos existen ambos nombres, abortamos para
         * poder revisar sus asignaciones antes de tocar datos.
         */
        if ($source !== null && $target !== null) {
            throw new \RuntimeException(
                "No se puede renombrar el permiso {$from}: ya existe {$to}. "
                . 'Revise los permisos y sus asignaciones antes de continuar.'
            );
        }

        /*
         * Actualizamos exclusivamente el nombre de la fila existente.
         * El ID no cambia, por lo que role_has_permissions y
         * model_has_permissions conservan todas sus relaciones.
         */
        DB::table('permissions')
            ->where('id', $source->id)
            ->update([
                'name' => $to,
            ]);
    }
};
