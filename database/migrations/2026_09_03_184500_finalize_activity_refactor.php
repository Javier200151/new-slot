<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->assertCanonicalSchema();

        if (Schema::hasTable('operation_slot_group')) {
            $rows = DB::table('operation_slot_group')->count();

            if ($rows !== 0) {
                throw new RuntimeException(
                    "No se elimina operation_slot_group porque contiene {$rows} filas. "
                    . 'Revísalas manualmente antes de finalizar el refactor.'
                );
            }

            $database = DB::getDatabaseName();
            $inboundForeignKeys = DB::table('information_schema.KEY_COLUMN_USAGE')
                ->where('REFERENCED_TABLE_SCHEMA', $database)
                ->where('REFERENCED_TABLE_NAME', 'operation_slot_group')
                ->count();

            if ($inboundForeignKeys !== 0) {
                throw new RuntimeException(
                    'No se elimina operation_slot_group porque existen foreign keys que la referencian.'
                );
            }

            Schema::drop('operation_slot_group');
        }

        if (Schema::hasTable('operation_slot_group')) {
            throw new RuntimeException('operation_slot_group sigue existiendo tras la migración final.');
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('operation_slot_group')) {
            return;
        }

        if (! Schema::hasTable('activities')) {
            throw new RuntimeException(
                'No se puede restaurar operation_slot_group porque no existe activities.'
            );
        }

        Schema::create('operation_slot_group', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('operation_id');
            $table->string('name');
            $table->integer('display_order')->default(0);
            $table->boolean('enable')->default(true);

            $table->foreign('operation_id', 'operation_slot_group_operation_id_foreign')
                ->references('id')
                ->on('activities');
        });
    }

    private function assertCanonicalSchema(): void
    {
        $requiredTables = [
            'activities',
            'activity_types',
            'activity_statuses',
            'activity_days',
            'activity_enemy_faction',
            'activity_day_assignments',
            'events',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                throw new RuntimeException(
                    "Falta la tabla canónica {$table}. Ejecuta primero las fases 1.5A-1.5C."
                );
            }
        }

        $requiredColumns = [
            'activities' => ['activity_type_id', 'activity_status_id'],
            'events' => ['activity_id'],
            'activity_enemy_faction' => ['activity_id', 'faction_id'],
            'activity_day_assignments' => ['activity_id', 'activity_day_id'],
        ];

        foreach ($requiredColumns as $table => $columns) {
            foreach ($columns as $column) {
                if (! Schema::hasColumn($table, $column)) {
                    throw new RuntimeException("Falta {$table}.{$column}.");
                }
            }
        }

        foreach ([
            'operations',
            'operations_type',
            'operation_status',
            'operation_day',
            'enemy_faction_operation',
            'operation_operation_day',
        ] as $legacyTable) {
            if (Schema::hasTable($legacyTable)) {
                throw new RuntimeException(
                    "La tabla histórica {$legacyTable} todavía existe; el esquema no está listo para finalizar."
                );
            }
        }
    }
};
