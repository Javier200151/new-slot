<?php

namespace App\Filament\Resources\ChangelogEntries\Pages;

use App\Filament\Resources\ChangelogEntries\ChangelogEntryResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditChangelogEntry extends EditRecord
{
    protected static string $resource = ChangelogEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
