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

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $parsed = app(CommunityClipService::class)->parse((string) ($data['url'] ?? ''));
        return array_merge($data, $parsed);
    }
}
