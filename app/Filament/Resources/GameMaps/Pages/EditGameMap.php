<?php

namespace App\Filament\Resources\GameMaps\Pages;

use App\Filament\Resources\GameMaps\GameMapResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditGameMap extends EditRecord
{
    protected static string $resource = GameMapResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
