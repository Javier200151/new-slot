<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Activity;
use Filament\Resources\Pages\CreateRecord;
use App\Services\CommunityNotificationService;
use App\Models\EventStatus;
use Illuminate\Validation\ValidationException;
use App\Support\ActivityTypeAccess;
use App\Support\ActivityTypeConfiguration;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

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
        /*
        |--------------------------------------------------------------------------
        | Recuperar operativo y estado solicitado
        |--------------------------------------------------------------------------
        */

        $operation = Activity::query()
            ->with('operationStatus')
            ->find($data['operation_id'] ?? null);

        if (! ActivityTypeAccess::can(
            auth()->user(),
            'events',
            'create',
            $operation?->operation_type_id,
        )) {
            throw ValidationException::withMessages([
                'data.operation_id' =>
                    'No tienes permiso para crear eventos de este tipo.',
            ]);
        }

        $submittedEventStatusId =
            $data['event_status_id'] ?? null;

        $eventStatus = $submittedEventStatusId
            ? EventStatus::query()->find(
                $submittedEventStatusId
            )
            : null;


        /*
        |--------------------------------------------------------------------------
        | Seguridad: operativo BORRADOR
        |--------------------------------------------------------------------------
        |
        | Si el operativo está en BORRADOR, el evento también debe quedar en
        | BORRADOR.
        |
        | Si Filament no envía event_status_id porque el selector está limitado
        | o deshabilitado, asignamos BORRADOR automáticamente.
        |
        | Si alguien intenta enviar explícitamente otro estado manipulando la
        | petición, seguimos bloqueándolo.
        |
        */

        if (
            $operation?->operationStatus?->name === 'BORRADOR'
        ) {
            $draftStatus = EventStatus::query()
                ->where('name', 'BORRADOR')
                ->firstOrFail();

            if (
                $submittedEventStatusId !== null
                && (int) $submittedEventStatusId
                    !== (int) $draftStatus->id
            ) {
                throw ValidationException::withMessages([
                    'data.event_status_id' =>
                        'Mientras el operativo esté en BORRADOR, '
                        . 'el evento también debe permanecer en BORRADOR.',
                ]);
            }

            $data['event_status_id'] =
                $draftStatus->id;

            $eventStatus =
                $draftStatus;
        }


        /*
        |--------------------------------------------------------------------------
        | Snapshot inicial del operativo
        |--------------------------------------------------------------------------
        */

        $data['name'] =
            $data['name']
            ?? $operation?->name;

        $data['orbat'] =
            $operation?->orbat;

        if ($operation?->editor_ally_id) {
            $data['multiclans'] = true;
        }

        return ActivityTypeConfiguration::normalizeEventData(
            $data,
            $operation?->id,
        );
    }

    protected function afterCreate(): void
    {
        $this->record->loadMissing(
            'eventStatus'
        );

        if (
            $this->record->eventStatus?->name
            !== 'ACTIVO'
        ) {
            return;
        }

        app(
            CommunityNotificationService::class
        )->eventPublished(
            $this->record
        );
    }
}
