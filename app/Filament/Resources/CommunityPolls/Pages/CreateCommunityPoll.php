<?php

namespace App\Filament\Resources\CommunityPolls\Pages;

use App\Filament\Resources\CommunityPolls\CommunityPollResource;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunityPoll extends CreateRecord
{
    protected static string $resource = CommunityPollResource::class;
}
