<?php

namespace App\Filament\Resources\EventStatuses\Pages;

use App\Filament\Resources\EventStatuses\EventStatusResource;
use Filament\Resources\Pages\CreateRecord;

class CreateEventStatus extends CreateRecord
{
    protected static string $resource = EventStatusResource::class;
}
