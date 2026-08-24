<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create(
            'operation_operation_day',
            function (Blueprint $table): void {
                $table->foreignId('operation_id')
                    ->constrained('operations')
                    ->cascadeOnDelete();

                $table->foreignId('operation_day_id')
                    ->constrained('operation_day')
                    ->restrictOnDelete();

                $table->primary([
                    'operation_id',
                    'operation_day_id',
                ]);
            }
        );

        /*
         * Conservamos los días que ya existen.
         */
        DB::table('operations')
            ->whereNotNull('day_id')
            ->orderBy('id')
            ->each(function ($operation): void {
                DB::table('operation_operation_day')
                    ->insertOrIgnore([
                        'operation_id' => $operation->id,
                        'operation_day_id' => $operation->day_id,
                    ]);
            });

        Schema::table('operations', function (Blueprint $table): void {
            $table->dropForeign(['day_id']);
            $table->dropColumn('day_id');
        });
    }

    public function down(): void
    {
        Schema::table('operations', function (Blueprint $table): void {
            $table->foreignId('day_id')
                ->nullable()
                ->constrained('operation_day')
                ->nullOnDelete();
        });

        DB::table('operation_operation_day')
            ->orderBy('operation_id')
            ->get()
            ->groupBy('operation_id')
            ->each(function ($days, $operationId): void {
                DB::table('operations')
                    ->where('id', $operationId)
                    ->update([
                        'day_id' => $days->first()->operation_day_id,
                    ]);
            });

        Schema::dropIfExists('operation_operation_day');
    }
};