<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('status', function (Blueprint $table): void {
            $table->string('color', 16)->default('#ffffff')->after('name');
            $table->boolean('is_system')->default(false)->after('color')->index();
        });

        // Todo estado existente en el momento de esta migración queda blindado,
        // independientemente de si procede del seeder original o fue creado
        // posteriormente de forma manual.
        DB::table('status')->update([
            'is_system' => true,
        ]);

        $legacyColors = [
            'ACTIVO' => '#4ade80',
            'RESERVA' => '#60a5fa',
            'CESADO' => '#f87171',
            'BAJA' => '#fb923c',
            'RECLUTA' => '#facc15',
            'USUARIO' => '#94a3b8',
        ];

        foreach ($legacyColors as $name => $color) {
            DB::table('status')
                ->whereRaw('UPPER(TRIM(name)) = ?', [$name])
                ->update(['color' => $color]);
        }
    }

    public function down(): void
    {
        Schema::table('status', function (Blueprint $table): void {
            $table->dropIndex(['is_system']);
            $table->dropColumn(['color', 'is_system']);
        });
    }
};
