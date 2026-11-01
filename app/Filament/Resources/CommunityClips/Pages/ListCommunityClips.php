<?php

namespace App\Filament\Resources\CommunityClips\Pages;

use App\Filament\Resources\CommunityClips\CommunityClipResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListCommunityClips extends ListRecords
{
    protected static string $resource = CommunityClipResource::class;
    protected function getHeaderActions(): array { return [CreateAction::make()]; }
}
