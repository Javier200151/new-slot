<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            throw new RuntimeException(
                'No existe la tabla activities. Ejecuta primero las fases 1.5A y 1.5B.'
            );
        }

        $this->assertNoTableCollision('operations_type', 'activity_types');
        $this->assertNoTableCollision('operation_status', 'activity_statuses');
        $this->assertNoTableCollision('operation_day', 'activity_days');
        $this->assertNoColumnCollision('activities', 'operation_type_id', 'activity_type_id');
        $this->assertNoColumnCollision('activities', 'operation_status_id', 'activity_status_id');
        $this->assertNoColumnCollision(
            'activity_day_assignments',
            'operation_day_id',
            'activity_day_id'
        );

        $before = $this->snapshotCounts();

        if (Schema::hasTable('operations_type')) {
            Schema::rename('operations_type', 'activity_types');
        }

        if (Schema::hasTable('operation_status')) {
            Schema::rename('operation_status', 'activity_statuses');
        }

        if (Schema::hasTable('operation_day')) {
            Schema::rename('operation_day', 'activity_days');
        }

        if (Schema::hasColumn('activities', 'operation_type_id')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->renameColumn('operation_type_id', 'activity_type_id');
            });
        }

        if (Schema::hasColumn('activities', 'operation_status_id')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->renameColumn('operation_status_id', 'activity_status_id');
            });
        }

        if (
            Schema::hasTable('activity_day_assignments')
            && Schema::hasColumn('activity_day_assignments', 'operation_day_id')
        ) {
            Schema::table('activity_day_assignments', function (Blueprint $table): void {
                $table->renameColumn('operation_day_id', 'activity_day_id');
            });
        }

        $this->assertUpState();
        $this->assertCountsUnchanged($before);
    }

    public function down(): void
    {
        $this->assertNoTableCollision('activity_types', 'operations_type');
        $this->assertNoTableCollision('activity_statuses', 'operation_status');
        $this->assertNoTableCollision('activity_days', 'operation_day');
        $this->assertNoColumnCollision('activities', 'activity_type_id', 'operation_type_id');
        $this->assertNoColumnCollision('activities', 'activity_status_id', 'operation_status_id');
        $this->assertNoColumnCollision(
            'activity_day_assignments',
            'activity_day_id',
            'operation_day_id'
        );

        $before = $this->snapshotCounts();

        if (
            Schema::hasTable('activity_day_assignments')
            && Schema::hasColumn('activity_day_assignments', 'activity_day_id')
        ) {
            Schema::table('activity_day_assignments', function (Blueprint $table): void {
                $table->renameColumn('activity_day_id', 'operation_day_id');
            });
        }

        if (Schema::hasColumn('activities', 'activity_status_id')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->renameColumn('activity_status_id', 'operation_status_id');
            });
        }

        if (Schema::hasColumn('activities', 'activity_type_id')) {
            Schema::table('activities', function (Blueprint $table): void {
                $table->renameColumn('activity_type_id', 'operation_type_id');
            });
        }

        if (Schema::hasTable('activity_days')) {
            Schema::rename('activity_days', 'operation_day');
        }

        if (Schema::hasTable('activity_statuses')) {
            Schema::rename('activity_statuses', 'operation_status');
        }

        if (Schema::hasTable('activity_types')) {
            Schema::rename('activity_types', 'operations_type');
        }

        $this->assertDownState();
        $this->assertCountsUnchanged($before);
    }

    private function assertNoTableCollision(string $source, string $target): void
    {
        $sourceExists = Schema::hasTable($source);
        $targetExists = Schema::hasTable($target);

        if ($sourceExists && $targetExists) {
            throw new RuntimeException(
                "No se puede renombrar {$source} a {$target}: existen ambas tablas."
            );
        }

        if (! $sourceExists && ! $targetExists) {
            throw new RuntimeException(
                "No se encuentra ni {$source} ni {$target}; el esquema no coincide con la fase esperada."
            );
        }
    }

    private function assertNoColumnCollision(
        string $table,
        string $source,
        string $target,
    ): void {
        if (! Schema::hasTable($table)) {
            throw new RuntimeException("No existe la tabla {$table}.");
        }

        $sourceExists = Schema::hasColumn($table, $source);
        $targetExists = Schema::hasColumn($table, $target);

        if ($sourceExists && $targetExists) {
            throw new RuntimeException(
                "No se puede renombrar {$table}.{$source} a {$target}: existen ambas columnas."
            );
        }

        if (! $sourceExists && ! $targetExists) {
            throw new RuntimeException(
                "No se encuentra ni {$table}.{$source} ni {$table}.{$target}."
            );
        }
    }

    private function assertUpState(): void
    {
        foreach (['activity_types', 'activity_statuses', 'activity_days'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla canónica {$table} tras la migración.");
            }
        }

        foreach (['activity_type_id', 'activity_status_id'] as $column) {
            if (! Schema::hasColumn('activities', $column)) {
                throw new RuntimeException("Falta activities.{$column} tras la migración.");
            }
        }

        if (! Schema::hasColumn('activity_day_assignments', 'activity_day_id')) {
            throw new RuntimeException(
                'Falta activity_day_assignments.activity_day_id tras la migración.'
            );
        }
    }

    private function assertDownState(): void
    {
        foreach (['operations_type', 'operation_status', 'operation_day'] as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException("Falta la tabla histórica {$table} tras el rollback.");
            }
        }

        foreach (['operation_type_id', 'operation_status_id'] as $column) {
            if (! Schema::hasColumn('activities', $column)) {
                throw new RuntimeException("Falta activities.{$column} tras el rollback.");
            }
        }

        if (! Schema::hasColumn('activity_day_assignments', 'operation_day_id')) {
            throw new RuntimeException(
                'Falta activity_day_assignments.operation_day_id tras el rollback.'
            );
        }
    }

    private function snapshotCounts(): array
    {
        $typeTable = Schema::hasTable('activity_types')
            ? 'activity_types'
            : 'operations_type';
        $statusTable = Schema::hasTable('activity_statuses')
            ? 'activity_statuses'
            : 'operation_status';
        $dayTable = Schema::hasTable('activity_days')
            ? 'activity_days'
            : 'operation_day';

        return [
            'activities' => DB::table('activities')->count(),
            'types' => DB::table($typeTable)->count(),
            'statuses' => DB::table($statusTable)->count(),
            'days' => DB::table($dayTable)->count(),
            'day_assignments' => DB::table('activity_day_assignments')->count(),
        ];
    }

    private function assertCountsUnchanged(array $before): void
    {
        $after = $this->snapshotCounts();

        if ($before !== $after) {
            throw new RuntimeException(
                'Los conteos cambiaron durante el rename de catálogos: '
                . json_encode(['before' => $before, 'after' => $after], JSON_UNESCAPED_UNICODE)
            );
        }
    }
};
