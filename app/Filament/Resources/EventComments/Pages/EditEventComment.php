<?php

namespace App\Filament\Resources\EventComments\Pages;

use App\Filament\Resources\EventComments\EventCommentResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEventComment extends EditRecord
{
    protected static string $resource = EventCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
