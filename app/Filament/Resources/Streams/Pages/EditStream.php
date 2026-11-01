<?php

namespace App\Filament\Resources\Streams\Pages;

use App\Filament\Resources\Streams\StreamResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;
use App\Services\HomepageVodService;

class EditStream extends EditRecord
{
    protected static string $resource = StreamResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->hasRole('admin')) {
            // La gestión global de VODs está reservada a los administradores,
            // incluso cuando alguien intenta enviarlo como una emisión normal.
            $candidate = new \App\Models\Stream($data);
            abort_if(! empty($data['is_vod']) || $candidate->isVodForHome(), 403);

            unset($data['is_vod'], $data['visible_on_home']);
        }
        if (! empty($data['is_vod'])) {
            $data['enabled'] = false;
            $data['ended_at'] = $data['ended_at'] ?: now();
            $data['published_at'] = $this->record->published_at ?? now();
        }
        return $data;
    }

    protected function afterSave(): void
    {
        app(HomepageVodService::class)->invalidateCache();
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()->after(fn () => app(HomepageVodService::class)->invalidateCache()),
        ];
    }
}
