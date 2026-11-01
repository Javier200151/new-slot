<?php

namespace App\Filament\Resources\Streams\Pages;

use App\Filament\Resources\Streams\StreamResource;
use Filament\Resources\Pages\CreateRecord;
use App\Services\HomepageVodService;

class CreateStream extends CreateRecord
{
    protected static string $resource = StreamResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        if (! auth()->user()?->hasRole('admin')) {
            // La gestión global de VODs está reservada a los administradores,
            // incluso cuando alguien intenta enviarlo como una emisión normal.
            $candidate = new \App\Models\Stream($data);
            abort_if(! empty($data['is_vod']) || $candidate->isVodForHome(), 403);

            // Los permisos de emisiones no conceden administración de VODs.
            unset($data['is_vod'], $data['visible_on_home']);
        }

        if (! empty($data['is_vod'])) {
            $data['auto_detected'] = false;
            $data['enabled'] = false;
            $data['published_at'] = now();
            $data['ended_at'] = $data['ended_at'] ?: now();
        }

        return $data;
    }

    protected function afterCreate(): void
    {
        app(HomepageVodService::class)->invalidateCache();
    }
}
