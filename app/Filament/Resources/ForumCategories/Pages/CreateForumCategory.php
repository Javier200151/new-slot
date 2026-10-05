<?php

namespace App\Filament\Resources\ForumCategories\Pages;

use App\Filament\Resources\ForumCategories\ForumCategoryResource;
use App\Models\ForumCategory;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Support\Str;

class CreateForumCategory extends CreateRecord
{
    protected static string $resource = ForumCategoryResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $data['slug'] = filled($data['slug'] ?? null)
            ? Str::slug((string) $data['slug'])
            : ForumCategory::uniqueSlug((string) ($data['title'] ?? 'categoria'));
        $data['channel'] = 'personal';
        $data['system_type'] = ForumCategory::TYPE_STANDARD;
        $data['is_system'] = false;

        return $data;
    }
}
