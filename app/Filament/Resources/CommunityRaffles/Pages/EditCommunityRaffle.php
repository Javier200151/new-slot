<?php

namespace App\Filament\Resources\CommunityRaffles\Pages;

use App\Filament\Resources\CommunityRaffles\CommunityRaffleResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommunityRaffle extends EditRecord
{
    protected static string $resource = CommunityRaffleResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()->visible(fn () => $this->record->status === 'draft' && ! $this->record->draws()->exists())];
    }
}
