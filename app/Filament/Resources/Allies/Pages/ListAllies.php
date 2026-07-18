<?php

namespace App\Filament\Resources\Allies\Pages;

use App\Filament\Resources\Allies\AllyResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAllies extends ListRecords
{
    protected static string $resource = AllyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
