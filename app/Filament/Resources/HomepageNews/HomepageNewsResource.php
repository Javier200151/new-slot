<?php

namespace App\Filament\Resources\HomepageNews;

use App\Filament\Resources\HomepageNews\Pages\CreateHomepageNews;
use App\Filament\Resources\HomepageNews\Pages\EditHomepageNews;
use App\Filament\Resources\HomepageNews\Pages\ListHomepageNews;
use App\Filament\Resources\HomepageNews\Schemas\HomepageNewsForm;
use App\Filament\Resources\HomepageNews\Tables\HomepageNewsTable;
use App\Models\HomepageNews;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HomepageNewsResource extends Resource
{
    protected static ?string $model = HomepageNews::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedNewspaper;
    protected static ?string $navigationLabel = 'Noticias de portada';
    protected static ?string $modelLabel = 'Noticia de portada';
    protected static ?string $pluralModelLabel = 'Noticias de portada';
    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema { return HomepageNewsForm::configure($schema); }
    public static function table(Table $table): Table { return HomepageNewsTable::configure($table); }
    public static function getRelations(): array { return []; }
    public static function getPages(): array
    {
        return [
            'index' => ListHomepageNews::route('/'),
            'create' => CreateHomepageNews::route('/create'),
            'edit' => EditHomepageNews::route('/{record}/edit'),
        ];
    }
}
