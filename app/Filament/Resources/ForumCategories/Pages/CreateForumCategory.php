<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use App\Models\ForumCategory;
use Filament\Resources\Pages\CreateRecord;

class CreateForumCategory extends CreateRecord
{
    protected static string $resource = ForumCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['channel'] = 'personal';
        $data['system_type'] = ForumCategory::TYPE_STANDARD;
        $data['process_type'] = null;
        $data['is_system'] = false;

        return $data;
    }
}
