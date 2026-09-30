<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('recruitment_periods')) {
            return;
        }

        /*
         * MySQL: permitimos que los periodos bootstrap de reclutas ya existentes
         * queden sin fecha hasta que un tutor/gestor la ajuste manualmente.
         */
        DB::statement('ALTER TABLE recruitment_periods MODIFY started_at TIMESTAMP NULL');

        DB::table('recruitment_periods')
            ->whereNull('ended_at')
            ->whereNotNull('open_user_id')
            ->whereIn('started_at_source', ['audit_log', 'migration_bootstrap'])
            ->update([
                'started_at' => null,
                'started_at_source' => 'manual_pending',
                'updated_at' => now(),
            ]);
    }

    public function down(): void
    {
        if (! Schema::hasTable('recruitment_periods')) {
            return;
        }

        /*
         * No volvemos a NOT NULL porque podría haber periodos bootstrap todavía
         * pendientes de una fecha manual. El rollback no debe inventar datos.
         */
    }
};
