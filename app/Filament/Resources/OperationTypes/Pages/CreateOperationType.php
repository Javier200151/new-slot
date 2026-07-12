<?php

namespace App\Filament\Resources\OperationTypes\Pages;

use App\Filament\Resources\OperationTypes\OperationTypeResource;
use Filament\Resources\Pages\CreateRecord;

class CreateOperationType extends CreateRecord
{
    protected static string $resource = OperationTypeResource::class;
}
