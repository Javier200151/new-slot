<?php

namespace App\Filament\Resources\CommunityPolls\Pages;

use App\Filament\Resources\CommunityPolls\CommunityPollResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommunityPoll extends EditRecord
{
    protected static string $resource = CommunityPollResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
