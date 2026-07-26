<?php

namespace App\Filament\Resources\Allies\Pages;

use App\Filament\Resources\Allies\AllyResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditAlly extends EditRecord
{
    protected static string $resource = AllyResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
