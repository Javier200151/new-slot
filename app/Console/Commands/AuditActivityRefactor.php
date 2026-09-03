<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class AuditActivityRefactor extends Command
{
    protected $signature = 'activities:audit-refactor';

    protected $description =
        'Valida el estado final del refactor Operativos -> Actividades sin modificar datos';

    public function handle(): int
    {
        $errors = [];

        $requiredTables = [
            'activities',
            'activity_types',
            'activity_statuses',
            'activity_days',
            'activity_enemy_faction',
            'activity_day_assignments',
            'events',
        ];

        $legacyTables = [
            'operations',
            'operations_type',
            'operation_status',
            'operation_day',
            'enemy_faction_operation',
            'operation_operation_day',
            'operation_slot_group',
        ];

        foreach ($requiredTables as $table) {
            if (! Schema::hasTable($table)) {
                $errors[] = "Falta tabla: {$table}";
            }
        }

        foreach ($legacyTables as $table) {
            if (Schema::hasTable($table)) {
                $errors[] = "Sigue existiendo tabla histórica: {$table}";
            }
        }

        $columnChecks = [
            ['activities', 'activity_type_id', true],
            ['activities', 'activity_status_id', true],
            ['events', 'activity_id', true],
            ['activity_enemy_faction', 'activity_id', true],
            ['activity_day_assignments', 'activity_id', true],
            ['activity_day_assignments', 'activity_day_id', true],
            ['activities', 'operation_type_id', false],
            ['activities', 'operation_status_id', false],
            ['events', 'operation_id', false],
            ['activity_enemy_faction', 'operation_id', false],
            ['activity_day_assignments', 'operation_id', false],
            ['activity_day_assignments', 'operation_day_id', false],
        ];

        foreach ($columnChecks as [$table, $column, $shouldExist]) {
            if (! Schema::hasTable($table)) {
                continue;
            }

            $exists = Schema::hasColumn($table, $column);

            if ($exists !== $shouldExist) {
                $errors[] = $shouldExist
                    ? "Falta columna: {$table}.{$column}"
                    : "Sigue existiendo columna histórica: {$table}.{$column}";
            }
        }

        $orphans = [];

        if (Schema::hasTable('activities') && Schema::hasColumn('events', 'activity_id')) {
            $orphans['events'] = DB::table('events as e')
                ->leftJoin('activities as a', 'a.id', '=', 'e.activity_id')
                ->whereNotNull('e.activity_id')
                ->whereNull('a.id')
                ->count();
        }

        if (Schema::hasTable('activity_enemy_faction')) {
            $orphans['enemy_factions'] = DB::table('activity_enemy_faction as p')
                ->leftJoin('activities as a', 'a.id', '=', 'p.activity_id')
                ->whereNull('a.id')
                ->count();
        }

        if (Schema::hasTable('activity_day_assignments')) {
            $orphans['activity_days'] = DB::table('activity_day_assignments as p')
                ->leftJoin('activities as a', 'a.id', '=', 'p.activity_id')
                ->whereNull('a.id')
                ->count();
        }

        foreach ($orphans as $relation => $count) {
            if ($count !== 0) {
                $errors[] = "Huérfanos en {$relation}: {$count}";
            }
        }

        $oldPermissionCount = Schema::hasTable('permissions')
            ? DB::table('permissions')
                ->where(function ($query): void {
                    $query->where('name', 'like', 'operations.%')
                        ->orWhere('name', 'like', 'operation-types.%')
                        ->orWhere('name', 'like', 'operation-statuses.%')
                        ->orWhere('name', 'like', 'operation-days.%');
                })
                ->count()
            : null;

        if ($oldPermissionCount !== null && $oldPermissionCount !== 0) {
            $errors[] = "Permisos históricos operation*: {$oldPermissionCount}";
        }

        $newPermissionCount = Schema::hasTable('permissions')
            ? DB::table('permissions')
                ->where(function ($query): void {
                    $query->where('name', 'like', 'activities.%')
                        ->orWhere('name', 'like', 'activity-types.%')
                        ->orWhere('name', 'like', 'activity-statuses.%')
                        ->orWhere('name', 'like', 'activity-days.%');
                })
                ->count()
            : null;

        $roleAssignmentCount = (
            Schema::hasTable('permissions')
            && Schema::hasTable('role_has_permissions')
        )
            ? DB::table('role_has_permissions as rhp')
                ->join('permissions as p', 'p.id', '=', 'rhp.permission_id')
                ->where(function ($query): void {
                    $query->where('p.name', 'like', 'activities.%')
                        ->orWhere('p.name', 'like', 'activity-types.%')
                        ->orWhere('p.name', 'like', 'activity-statuses.%')
                        ->orWhere('p.name', 'like', 'activity-days.%');
                })
                ->count()
            : null;

        $legacyAuditSubjects = Schema::hasTable('activity_log')
            ? DB::table('activity_log')
                ->whereIn('subject_type', [
                    'App\\Models\\Operation',
                    'App\\Models\\OperationType',
                    'App\\Models\\OperationStatus',
                    'App\\Models\\OperationDay',
                ])
                ->count()
            : null;

        $this->table(
            ['Métrica', 'Valor'],
            [
                ['activities', Schema::hasTable('activities') ? DB::table('activities')->count() : 'N/A'],
                ['activity_types', Schema::hasTable('activity_types') ? DB::table('activity_types')->count() : 'N/A'],
                ['activity_statuses', Schema::hasTable('activity_statuses') ? DB::table('activity_statuses')->count() : 'N/A'],
                ['activity_days', Schema::hasTable('activity_days') ? DB::table('activity_days')->count() : 'N/A'],
                ['events', Schema::hasTable('events') ? DB::table('events')->count() : 'N/A'],
                ['orphan_events', $orphans['events'] ?? 'N/A'],
                ['orphan_enemy_factions', $orphans['enemy_factions'] ?? 'N/A'],
                ['orphan_activity_days', $orphans['activity_days'] ?? 'N/A'],
                ['activity_permissions', $newPermissionCount ?? 'N/A'],
                ['activity_role_assignments', $roleAssignmentCount ?? 'N/A'],
                ['old_operation_permissions', $oldPermissionCount ?? 'N/A'],
                ['legacy_audit_subject_rows', $legacyAuditSubjects ?? 'N/A'],
            ],
        );

        if ($errors !== []) {
            $this->newLine();
            $this->error('El refactor todavía tiene incidencias:');

            foreach ($errors as $error) {
                $this->line(" - {$error}");
            }

            return self::FAILURE;
        }

        $this->newLine();
        $this->info('OK: el esquema principal Operativos -> Actividades está finalizado y sin huérfanos.');
        $this->line(
            'Nota: se conservan las clases App\\Models\\Operation/OperationType/OperationStatus '
            . 'para resolver activity_log histórico y los redirects /operativos para enlaces antiguos.'
        );

        return self::SUCCESS;
    }
}
