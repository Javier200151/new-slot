<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operations_type', function (Blueprint $table): void {
            $table->boolean('uses_enemy_factions')->default(true);
            $table->boolean('uses_event_result')->default(true);
            $table->boolean('supports_ocap')->default(true);
            $table->boolean('supports_respawn')->default(true);
            $table->boolean('supports_jip')->default(true);
            $table->boolean('awards_metopa')->default(false);
        });

        // Configuración inicial esperada para el tipo CURSO existente.
        DB::table('operations_type')
            ->whereRaw('LOWER(name) = ?', ['curso'])
            ->update([
                'uses_enemy_factions' => false,
                'uses_event_result' => false,
                'supports_ocap' => false,
                'supports_respawn' => false,
                'supports_jip' => false,
                'awards_metopa' => true,
            ]);
    }

    public function down(): void
    {
        Schema::table('operations_type', function (Blueprint $table): void {
            $table->dropColumn([
                'uses_enemy_factions',
                'uses_event_result',
                'supports_ocap',
                'supports_respawn',
                'supports_jip',
                'awards_metopa',
            ]);
        });
    }
};
