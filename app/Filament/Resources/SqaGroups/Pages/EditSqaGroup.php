<?php

namespace App\Filament\Resources\SqaGroups\Pages;

use App\Filament\Resources\SqaGroups\SqaGroupResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditSqaGroup extends EditRecord
{
    protected static string $resource = SqaGroupResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
