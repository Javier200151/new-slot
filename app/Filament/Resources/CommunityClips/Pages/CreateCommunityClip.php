<?php

namespace App\Filament\Resources\CommunityClips\Pages;

use App\Filament\Resources\CommunityClips\CommunityClipResource;
use App\Services\CommunityClipService;
use Filament\Resources\Pages\CreateRecord;

class CreateCommunityClip extends CreateRecord
{
    protected static string $resource = CommunityClipResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $parsed = app(CommunityClipService::class)->parse((string) ($data['url'] ?? ''));
        return array_merge($data, $parsed, ['source' => 'manual', 'clip_created_at' => now()]);
    }
}
