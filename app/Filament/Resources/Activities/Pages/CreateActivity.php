<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\AuditLogger;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityEditorSelection;
use App\Support\ActivityTypeConfiguration;
use Illuminate\Validation\ValidationException;

class CreateActivity extends CreateRecord
{
    protected static string $resource = ActivityResource::class;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->submit(null)
                ->action('create')
                ->label('Crear')
                ->icon('heroicon-o-check'),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ActivityEditorSelection::resolveChoice($data);
        $data = ActivityTypeConfiguration::normalizeActivityData($data);

        $operationTypeId = $data['operation_type_id'] ?? null;

        if (! ActivityTypeAccess::can(
            auth()->user(),
            'activities',
            'create',
            $operationTypeId,
        )) {
            throw ValidationException::withMessages([
                'data.operation_type_id' =>
                    'No tienes permiso para crear actividades de este tipo.',
            ]);
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        /*
        |--------------------------------------------------------------------------
        | Días del operativo
        |--------------------------------------------------------------------------
        */

        $days =
            $this->record
                ->days()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();

        if ($days !== []) {
            app(AuditLogger::class)
                ->change(
                    subject: $this->record,

                    event: 'operation_days_updated',

                    old: [
                        'days' => [],
                    ],

                    new: [
                        'days' => $days,
                    ],

                    properties: [
                        'relation' => 'days',

                        'table' =>
                            'operation_operation_day',

                        'action' => 'initial_assignment',
                    ],
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Facciones enemigas
        |--------------------------------------------------------------------------
        */

        $this->record->loadMissing('operationType');

        if (! ($this->record->operationType?->usesEnemyFactions() ?? false)) {
            $this->record->enemyFactions()->detach();
        }

        $enemyFactions =
            $this->record
                ->enemyFactions()
                ->orderBy('name')
                ->pluck('name')
                ->values()
                ->all();

        if ($enemyFactions !== []) {
            app(AuditLogger::class)
                ->change(
                    subject: $this->record,

                    event:
                        'operation_enemy_factions_updated',

                    old: [
                        'enemy_factions' => [],
                    ],

                    new: [
                        'enemy_factions' =>
                            $enemyFactions,
                    ],

                    properties: [
                        'relation' =>
                            'enemyFactions',

                        'table' =>
                            'enemy_faction_operation',

                        'action' =>
                            'initial_assignment',
                    ],
                );
        }
    }
}
