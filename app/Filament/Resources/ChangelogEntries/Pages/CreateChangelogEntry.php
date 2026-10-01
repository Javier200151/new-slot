<?php

namespace App\Filament\Resources\ChangelogEntries\Pages;

use App\Filament\Resources\ChangelogEntries\ChangelogEntryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateChangelogEntry extends CreateRecord
{
    protected static string $resource = ChangelogEntryResource::class;
}
