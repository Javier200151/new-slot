<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_status', function (Blueprint $table): void {
            $table->boolean('is_system')->default(false)->after('name');
        });

        DB::table('event_status')
            ->whereIn('name', ['ACTIVO', 'BORRADOR', 'CANCELADO', 'FINALIZADO'])
            ->update(['is_system' => true]);
    }

    public function down(): void
    {
        Schema::table('event_status', function (Blueprint $table): void {
            $table->dropColumn('is_system');
        });
    }
};
