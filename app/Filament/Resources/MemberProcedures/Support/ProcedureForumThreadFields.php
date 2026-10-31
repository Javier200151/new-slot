<?php

namespace App\Filament\Resources\MemberProcedures\Support;

use App\Models\CommunityPost;
use App\Models\ForumCategory;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use LogicException;

class ProcedureForumThreadFields
{
    /** La categoría y el hilo son opcionales; se pueden completar posteriormente. */
    public static function fields(): array
    {
        return [
            Select::make('forum_category_id')
                ->label('Categoría del foro')
                ->options(fn (): array => ForumCategory::query()
                    ->where('channel', 'personal')->where('is_enabled', true)
                    ->orderBy('sort_order')->pluck('title', 'id')->all())
                ->searchable()
                ->live()
                ->afterStateUpdated(fn (Set $set) => $set('forum_post_id', null))
                ->placeholder('Sin hilo asociado'),
            Select::make('forum_post_id')
                ->label('Hilo para «Ver más»')
                ->searchable()
                ->visible(fn (Get $get): bool => filled($get('forum_category_id')))
                ->getSearchResultsUsing(function (string $search, Get $get): array {
                    $categoryId = (int) $get('forum_category_id');
                    if ($categoryId <= 0) {
                        return [];
                    }

                    return CommunityPost::query()
                        ->where('forum_category_id', $categoryId)
                        ->where('channel', 'personal')
                        ->where('title', 'like', '%'.$search.'%')
                        ->orderByDesc('created_at')
                        ->limit(40)
                        ->pluck('title', 'id')->all();
                })
                ->getOptionLabelUsing(fn ($value): ?string => CommunityPost::query()
                    ->whereKey((int) $value)->value('title'))
                ->placeholder('Sin hilo asociado · escribe para buscar'),
        ];
    }

    public static function validatedPostId(array $data): ?int
    {
        $postId = (int) ($data['forum_post_id'] ?? 0);
        if ($postId <= 0) {
            return null;
        }

        $categoryId = (int) ($data['forum_category_id'] ?? 0);
        $valid = CommunityPost::query()
            ->whereKey($postId)
            ->where('channel', 'personal')
            ->where('forum_category_id', $categoryId)
            ->whereHas('forumCategory', fn ($query) => $query
                ->where('channel', 'personal')->where('is_enabled', true))
            ->exists();

        if (! $valid) {
            throw new LogicException('El hilo seleccionado no está disponible en esa categoría.');
        }

        return $postId;
    }
}
