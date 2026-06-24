<?php

namespace App\Filament\Resources\Metopas\Pages;

use App\Filament\Resources\Metopas\MetopaResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditMetopa extends EditRecord
{
    protected static string $resource = MetopaResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
