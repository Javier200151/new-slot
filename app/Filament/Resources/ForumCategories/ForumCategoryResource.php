<?php

namespace App\Filament\Resources\ForumCategories;

use App\Filament\Resources\ForumCategories\Pages\CreateForumCategory;
use App\Filament\Resources\ForumCategories\Pages\EditForumCategory;
use App\Filament\Resources\ForumCategories\Pages\ListForumCategories;
use App\Filament\Resources\ForumCategories\Schemas\ForumCategoryForm;
use App\Filament\Resources\ForumCategories\Tables\ForumCategoriesTable;
use App\Models\ForumCategory;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class ForumCategoryResource extends Resource
{
    protected static ?string $model = ForumCategory::class;

    protected static string|UnitEnum|null $navigationGroup = 'Comunidad';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-chat-bubble-left-right';
    protected static ?string $navigationLabel = 'Categorías del foro';
    protected static ?string $modelLabel = 'Categoría del foro';
    protected static ?string $pluralModelLabel = 'Categorías del foro';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return ForumCategoryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ForumCategoriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListForumCategories::route('/'),
            'create' => CreateForumCategory::route('/create'),
            'edit' => EditForumCategory::route('/{record}/edit'),
        ];
    }
}
