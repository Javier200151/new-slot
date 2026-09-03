<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('activities')) {
            throw new \RuntimeException(
                'No existe la tabla activities. Ejecuta primero la migración que renombra operations a activities.'
            );
        }

        // events.operation_id -> events.activity_id
        if (Schema::hasTable('events')) {
            if (
                Schema::hasColumn('events', 'operation_id')
                && ! Schema::hasColumn('events', 'activity_id')
            ) {
                Schema::table('events', function (Blueprint $table): void {
                    $table->renameColumn('operation_id', 'activity_id');
                });
            }
        }

        // enemy_faction_operation -> activity_enemy_faction
        if (
            Schema::hasTable('enemy_faction_operation')
            && ! Schema::hasTable('activity_enemy_faction')
        ) {
            Schema::rename('enemy_faction_operation', 'activity_enemy_faction');
        }

        if (
            Schema::hasTable('activity_enemy_faction')
            && Schema::hasColumn('activity_enemy_faction', 'operation_id')
            && ! Schema::hasColumn('activity_enemy_faction', 'activity_id')
        ) {
            Schema::table('activity_enemy_faction', function (Blueprint $table): void {
                $table->renameColumn('operation_id', 'activity_id');
            });
        }

        // operation_operation_day -> activity_day_assignments
        // operation_day_id se conserva hasta la fase de renombrado del catálogo de días.
        if (
            Schema::hasTable('operation_operation_day')
            && ! Schema::hasTable('activity_day_assignments')
        ) {
            Schema::rename('operation_operation_day', 'activity_day_assignments');
        }

        if (
            Schema::hasTable('activity_day_assignments')
            && Schema::hasColumn('activity_day_assignments', 'operation_id')
            && ! Schema::hasColumn('activity_day_assignments', 'activity_id')
        ) {
            Schema::table('activity_day_assignments', function (Blueprint $table): void {
                $table->renameColumn('operation_id', 'activity_id');
            });
        }

        $this->assertUpState();
    }

    public function down(): void
    {
        if (
            Schema::hasTable('activity_day_assignments')
            && Schema::hasColumn('activity_day_assignments', 'activity_id')
            && ! Schema::hasColumn('activity_day_assignments', 'operation_id')
        ) {
            Schema::table('activity_day_assignments', function (Blueprint $table): void {
                $table->renameColumn('activity_id', 'operation_id');
            });
        }

        if (
            Schema::hasTable('activity_day_assignments')
            && ! Schema::hasTable('operation_operation_day')
        ) {
            Schema::rename('activity_day_assignments', 'operation_operation_day');
        }

        if (
            Schema::hasTable('activity_enemy_faction')
            && Schema::hasColumn('activity_enemy_faction', 'activity_id')
            && ! Schema::hasColumn('activity_enemy_faction', 'operation_id')
        ) {
            Schema::table('activity_enemy_faction', function (Blueprint $table): void {
                $table->renameColumn('activity_id', 'operation_id');
            });
        }

        if (
            Schema::hasTable('activity_enemy_faction')
            && ! Schema::hasTable('enemy_faction_operation')
        ) {
            Schema::rename('activity_enemy_faction', 'enemy_faction_operation');
        }

        if (
            Schema::hasTable('events')
            && Schema::hasColumn('events', 'activity_id')
            && ! Schema::hasColumn('events', 'operation_id')
        ) {
            Schema::table('events', function (Blueprint $table): void {
                $table->renameColumn('activity_id', 'operation_id');
            });
        }
    }

    private function assertUpState(): void
    {
        $checks = [
            ['events', 'activity_id'],
            ['activity_enemy_faction', 'activity_id'],
            ['activity_day_assignments', 'activity_id'],
        ];

        foreach ($checks as [$table, $column]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                throw new \RuntimeException(
                    "Estado inesperado tras el rename: falta {$table}.{$column}."
                );
            }
        }
    }
};
