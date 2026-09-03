<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $hasOperations = Schema::hasTable('operations');
        $hasActivities = Schema::hasTable('activities');

        if ($hasOperations && $hasActivities) {
            throw new \RuntimeException(
                'No se puede renombrar operations a activities: ambas tablas existen.'
            );
        }

        if ($hasActivities) {
            // Estado ya migrado. No hacemos nada para mantener la migración idempotente
            // ante una recuperación manual controlada.
            return;
        }

        if (! $hasOperations) {
            throw new \RuntimeException(
                'No se puede renombrar operations a activities: no existe la tabla operations.'
            );
        }

        Schema::rename('operations', 'activities');
    }

    public function down(): void
    {
        $hasOperations = Schema::hasTable('operations');
        $hasActivities = Schema::hasTable('activities');

        if ($hasOperations && $hasActivities) {
            throw new \RuntimeException(
                'No se puede revertir activities a operations: ambas tablas existen.'
            );
        }

        if ($hasOperations) {
            return;
        }

        if (! $hasActivities) {
            throw new \RuntimeException(
                'No se puede revertir activities a operations: no existe la tabla activities.'
            );
        }

        Schema::rename('activities', 'operations');
    }
};
