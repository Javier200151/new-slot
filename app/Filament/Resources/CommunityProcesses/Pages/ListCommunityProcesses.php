<?php

namespace App\Filament\Resources\CommunityProcesses\Pages;

use App\Filament\Resources\CommunityProcesses\CommunityProcessResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityProcesses extends ListRecords
{
    protected static string $resource = CommunityProcessResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
