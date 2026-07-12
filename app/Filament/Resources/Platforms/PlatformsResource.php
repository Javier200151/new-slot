<?php

namespace App\Filament\Resources\Platforms;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\Platforms\Pages\CreatePlatforms;
use App\Filament\Resources\Platforms\Pages\EditPlatforms;
use App\Filament\Resources\Platforms\Pages\ListPlatforms;
use App\Filament\Resources\Platforms\Schemas\PlatformsForm;
use App\Filament\Resources\Platforms\Tables\PlatformsTable;
use App\Models\Platform;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;
class PlatformsResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 1;
    protected static ?string $model = Platform::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Plataforma';

    protected static ?string $pluralModelLabel = 'Plataformas';

    protected static ?string $navigationLabel = 'Plataformas';

    public static function form(Schema $schema): Schema
    {
        return PlatformsForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PlatformsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPlatforms::route('/'),
            'create' => CreatePlatforms::route('/create'),
            'edit' => EditPlatforms::route('/{record}/edit'),
        ];
    }
}
