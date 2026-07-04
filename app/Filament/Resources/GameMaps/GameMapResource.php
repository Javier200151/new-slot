<?php

namespace App\Filament\Resources\GameMaps;

use App\Filament\Resources\GameMaps\Pages\CreateGameMap;
use App\Filament\Resources\GameMaps\Pages\EditGameMap;
use App\Filament\Resources\GameMaps\Pages\ListGameMaps;
use App\Filament\Resources\GameMaps\Schemas\GameMapForm;
use App\Filament\Resources\GameMaps\Tables\GameMapsTable;
use App\Models\GameMap;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class GameMapResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Operativos';
    protected static ?int $navigationSort = 4;

    protected static ?string $modelLabel = 'Mapa';

    protected static ?string $pluralModelLabel = 'Mapas';

    protected static ?string $navigationLabel = 'Mapas';

    protected static ?string $model = GameMap::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return GameMapForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return GameMapsTable::configure($table);
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
            'index' => ListGameMaps::route('/'),
            'create' => CreateGameMap::route('/create'),
            'edit' => EditGameMap::route('/{record}/edit'),
        ];
    }
}
