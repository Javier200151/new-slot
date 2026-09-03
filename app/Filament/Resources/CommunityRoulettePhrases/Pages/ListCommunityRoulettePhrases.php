<?php

namespace App\Filament\Resources\CommunityRoulettePhrases\Pages;

use App\Filament\Resources\CommunityRoulettePhrases\CommunityRoulettePhraseResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityRoulettePhrases extends ListRecords
{
    protected static string $resource = CommunityRoulettePhraseResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
