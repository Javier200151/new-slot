<?php

namespace App\Filament\Resources\Activities\Pages;

use App\Filament\Resources\Activities\ActivityResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\AuditLogger;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityEditorSelection;
use App\Support\ActivityTypeConfiguration;
use App\Support\ActivityPbo;
use App\Support\OrbatGlobalCommand;
use App\Filament\Resources\Activities\Pages\Concerns\ConfirmsPboReplacement;
use App\Models\ActivityStatus;
use Illuminate\Validation\ValidationException;
use App\Services\PersistentCampaignPbo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class CreateActivity extends CreateRecord
{
    use ConfirmsPboReplacement;
    protected static string $resource = ActivityResource::class;
    protected bool $inheritedCampaignPbo = false;

    protected function getHeaderActions(): array
    {
        return [
            $this->getCreateFormAction()
                ->submit(null)
                ->action('create')
                ->label('Crear')
                ->icon('heroicon-o-check'),

            $this->approvePboReplacementAction(),

            $this->getCancelFormAction()
                ->label('Cancelar'),
        ];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data = ActivityEditorSelection::resolveChoice($data);
        $data = ActivityTypeConfiguration::normalizeActivityData($data);
        $source = PersistentCampaignPbo::source(isset($data['campaign_id']) ? (int) $data['campaign_id'] : null);
        $data = app(PersistentCampaignPbo::class)->inherit($data, null);
        $this->inheritedCampaignPbo = $source && ($data['pbo_path'] ?? null) === $source->pbo_path;
        $data = ActivityPbo::prepare($data);
        if ($this->inheritedCampaignPbo) {
            foreach (['pbo', 'pbo_size_bytes', 'pbo_uploaded_by', 'pbo_uploaded_at'] as $column) {
                $data[$column] = $source->{$column};
            }
        }
        $data['pbo_key'] = PersistentCampaignPbo::campaignKey($data['campaign_id'] ?? null);
        $this->assertPboReplacementApproved($data['pbo_path'] ?? null);

        $activityTypeId = $data['activity_type_id'] ?? null;

        if (! ActivityTypeAccess::can(
            auth()->user(),
            'activities',
            'create',
            $activityTypeId,
        )) {
            throw ValidationException::withMessages([
                'data.activity_type_id' =>
                    'No tienes permiso para crear actividades de este tipo.',
            ]);
        }

        // El modelo Activity crea automáticamente el slot obligatorio antes de
        // persistir la actividad, incluso cuando nace directamente publicada.

        return $data;
    }

    protected function handleRecordCreation(array $data): Model
    {
        return DB::transaction(function () use ($data): Model {
            $model = static::getModel();
            $record = $model::create($data);
            app(PersistentCampaignPbo::class)->reconcile($record, null, null);
            return $record;
        });
    }

    protected function afterCreate(): void
    {
        if ($this->record->pbo_path && $this->inheritedCampaignPbo
            && $this->record->pboExport?->status !== 'synced') {
            app(\App\Services\ActivityPboExportService::class)->queueKey($this->record->pbo_key);
        }
        if ($this->record->pbo_path && ! $this->inheritedCampaignPbo) {
            app(\App\Services\ActivityPboExportService::class)->queue(
                $this->record,
                strcasecmp($this->approvedPboReplacementName ?? '', basename($this->record->pbo_path)) === 0,
            );
        }
        $this->approvedPboReplacementName = null;

        /*
        |--------------------------------------------------------------------------
        | Días del actividad
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

                    event: 'activity_days_updated',

                    old: [
                        'days' => [],
                    ],

                    new: [
                        'days' => $days,
                    ],

                    properties: [
                        'relation' => 'days',

                        'table' =>
                            'activity_day_assignments',

                        'action' => 'initial_assignment',
                    ],
                );
        }


        /*
        |--------------------------------------------------------------------------
        | Facciones enemigas
        |--------------------------------------------------------------------------
        */

        $this->record->loadMissing('activityType');

        if (! ($this->record->activityType?->usesEnemyFactions() ?? false)) {
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
                        'activity_enemy_factions_updated',

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
                            'activity_enemy_faction',

                        'action' =>
                            'initial_assignment',
                    ],
                );
        }
    }
}
