<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Primer ingreso real como recluta, distinto de la primera promoción
            // a ACTIVO (member_at) y de los sucesivos recruitment_periods.
            $table->date('recruited_at')->nullable()->after('member_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn('recruited_at');
        });
    }
};
