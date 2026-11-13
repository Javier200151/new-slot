<?php

namespace App\Filament\Resources\CommunityRaffles\Pages;

use App\Filament\Resources\CommunityRaffles\CommunityRaffleResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityRaffles extends ListRecords
{
    protected static string $resource = CommunityRaffleResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
