<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const GUARD = 'web';

    /**
     * Prefijos de permisos históricos y sus equivalentes canónicos.
     *
     * El cambio se realiza sobre la misma fila de `permissions`: nunca se
     * eliminan ni recrean permisos, por lo que sus IDs y todas las relaciones
     * de `role_has_permissions` / `model_has_permissions` permanecen intactas.
     */
    private const PREFIXES = [
        'operations.' => 'activities.',
        'operation-types.' => 'activity-types.',
        'operation-statuses.' => 'activity-statuses.',
        'operation-days.' => 'activity-days.',
    ];

    public function up(): void
    {
        $this->renamePrefixes(self::PREFIXES);
    }

    public function down(): void
    {
        $reverse = [];

        foreach (self::PREFIXES as $old => $new) {
            $reverse[$new] = $old;
        }

        $this->renamePrefixes($reverse);
    }

    private function renamePrefixes(array $prefixes): void
    {
        if (! Schema::hasTable('permissions')) {
            return;
        }

        DB::transaction(function () use ($prefixes): void {
            $permissions = DB::table('permissions')
                ->where('guard_name', self::GUARD)
                ->orderBy('id')
                ->get(['id', 'name']);

            $existingByName = $permissions
                ->keyBy('name');

            $renames = [];

            foreach ($permissions as $permission) {
                foreach ($prefixes as $fromPrefix => $toPrefix) {
                    if (! str_starts_with($permission->name, $fromPrefix)) {
                        continue;
                    }

                    $targetName = $toPrefix
                        . substr($permission->name, strlen($fromPrefix));

                    $target = $existingByName->get($targetName);

                    /*
                     * Nunca fusionamos dos permisos automáticamente. Si por
                     * divergencia entre entornos ya existe el nombre destino
                     * con otro ID, abortamos antes de modificar ninguna fila.
                     */
                    if ($target !== null && (int) $target->id !== (int) $permission->id) {
                        throw new RuntimeException(
                            "No se puede renombrar {$permission->name} a {$targetName}: "
                            . "el permiso destino ya existe con ID {$target->id}."
                        );
                    }

                    $renames[] = [
                        'id' => (int) $permission->id,
                        'from' => $permission->name,
                        'to' => $targetName,
                    ];

                    break;
                }
            }

            foreach ($renames as $rename) {
                DB::table('permissions')
                    ->where('id', $rename['id'])
                    ->where('name', $rename['from'])
                    ->where('guard_name', self::GUARD)
                    ->update([
                        'name' => $rename['to'],
                    ]);
            }
        });
    }
};
