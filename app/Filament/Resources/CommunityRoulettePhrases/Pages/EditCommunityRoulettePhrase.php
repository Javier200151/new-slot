<?php

namespace App\Filament\Resources\CommunityRoulettePhrases\Pages;

use App\Filament\Resources\CommunityRoulettePhrases\CommunityRoulettePhraseResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditCommunityRoulettePhrase extends EditRecord
{
    protected static string $resource = CommunityRoulettePhraseResource::class;

    protected function getHeaderActions(): array
    {
        return [DeleteAction::make()];
    }
}
