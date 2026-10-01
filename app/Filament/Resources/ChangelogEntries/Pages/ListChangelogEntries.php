<?php

namespace App\Filament\Resources\ChangelogEntries\Pages;

use App\Filament\Resources\ChangelogEntries\ChangelogEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListChangelogEntries extends ListRecords
{
    protected static string $resource = ChangelogEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
