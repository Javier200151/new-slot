<?php

namespace App\Filament\Resources\GameMaps\Pages;

use App\Filament\Resources\GameMaps\GameMapResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListGameMaps extends ListRecords
{
    protected static string $resource = GameMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
