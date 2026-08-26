<?php

namespace App\Filament\Resources\Operations\Pages;

use App\Filament\Resources\Operations\OperationResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\AuditLogger;

class CreateOperation extends CreateRecord
{
    protected static string $resource = OperationResource::class;

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
