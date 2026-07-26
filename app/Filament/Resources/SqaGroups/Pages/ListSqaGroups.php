<?php

namespace App\Filament\Resources\SqaGroups\Pages;

use App\Filament\Resources\SqaGroups\SqaGroupResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListSqaGroups extends ListRecords
{
    protected static string $resource = SqaGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
