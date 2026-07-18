<?php

namespace App\Filament\Resources\EventComments\Pages;

use App\Filament\Resources\EventComments\EventCommentResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventComments extends ListRecords
{
    protected static string $resource = EventCommentResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
