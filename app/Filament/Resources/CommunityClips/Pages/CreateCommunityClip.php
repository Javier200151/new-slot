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
        $meta = $parsed['platform'] === 'youtube'
            ? app(CommunityClipService::class)->youtubeVideo($parsed['external_id'])
            : app(CommunityClipService::class)->twitchClip($parsed['external_id']);

        return array_merge($data, $parsed, [
            'source' => 'manual',
            'title' => trim((string) ($data['title'] ?? '')) ?: (string) ($meta['title'] ?? 'Clip de la comunidad'),
            'thumbnail_url' => $meta['thumbnail_url'] ?? ($parsed['platform'] === 'youtube'
                ? 'https://i.ytimg.com/vi/' . $parsed['external_id'] . '/hqdefault.jpg'
                : null),
            'clip_created_at' => $meta['created_at'] ?? now(),
        ]);
    }
}
