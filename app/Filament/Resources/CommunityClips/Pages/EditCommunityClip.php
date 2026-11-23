<?php

namespace App\Filament\Resources\CommunityClips\Pages;

use App\Filament\Resources\CommunityClips\CommunityClipResource;
use App\Services\CommunityClipService;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditCommunityClip extends EditRecord
{
    protected static string $resource = CommunityClipResource::class;
    protected function getHeaderActions(): array { return [DeleteAction::make(), RestoreAction::make()]; }

    protected function afterSave(): void
    {
        $first = $this->record->protagonists()->orderBy('users.id')->value('users.id');
        $this->record->update(['protagonist_user_id' => $first]);
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $parsed = app(CommunityClipService::class)->parse((string) ($data['url'] ?? ''));
        return array_merge($data, $parsed);
    }
}
