<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Repairs older deployments where the v71 approval columns were not created
 * (or were later removed), although Laravel considers that migration applied.
 * Keep existing values intact and do not modify PBO files or export records.
 */
return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('activity_pbo_exports')) {
            throw new \RuntimeException('No existe la tabla activity_pbo_exports. Comprueba las migraciones anteriores.');
        }

        $missingReplacement = ! Schema::hasColumn('activity_pbo_exports', 'replacement_approved');
        $missingHtml = ! Schema::hasColumn('activity_pbo_exports', 'html_overwrite_approved');

        if (! $missingReplacement && ! $missingHtml) {
            return;
        }

        Schema::table('activity_pbo_exports', function (Blueprint $table) use ($missingReplacement, $missingHtml): void {
            if ($missingReplacement) {
                $table->boolean('replacement_approved')->default(false);
            }

            if ($missingHtml) {
                $table->boolean('html_overwrite_approved')->default(false);
            }
        });
    }

    public function down(): void
    {
        // Intentionally non-destructive: these flags may predate this repair.
        // Deleting them on rollback would break PBO replacement protection.
    }
};
