<?php

namespace App\Filament\Resources\Events\Pages;

use App\Filament\Resources\Events\EventResource;
use App\Models\Operation;
use Filament\Resources\Pages\CreateRecord;

class CreateEvent extends CreateRecord
{
    protected static string $resource = EventResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $operation = Operation::query()->find($data['operation_id']);

        $data['name'] = $data['name'] ?? $operation?->name;
        $data['orbat'] = $operation?->orbat;

        return $data;
    }
}
