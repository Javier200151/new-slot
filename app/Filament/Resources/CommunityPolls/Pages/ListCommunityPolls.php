<?php

namespace App\Filament\Resources\CommunityPolls\Pages;

use App\Filament\Resources\CommunityPolls\CommunityPollResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityPolls extends ListRecords
{
    protected static string $resource = CommunityPollResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
