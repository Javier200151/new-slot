<?php

namespace App\Filament\Resources\Factions\Pages;

use App\Filament\Resources\Factions\FactionResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListFactions extends ListRecords
{
    protected static string $resource = FactionResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
