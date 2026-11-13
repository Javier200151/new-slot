<?php

namespace App\Filament\Resources\CommunityRaffles\Pages;

use App\Filament\Resources\CommunityRaffles\CommunityRaffleResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunityRaffle extends CreateRecord
{
    protected static string $resource = CommunityRaffleResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['created_by'] = auth()->id();
        $data['status'] = 'draft';
        $data['active_key'] = null;

        return $data;
    }
}
