<?php

namespace App\Filament\Resources\Factions;

use App\Filament\Resources\Factions\Pages\CreateFaction;
use App\Filament\Resources\Factions\Pages\EditFaction;
use App\Filament\Resources\Factions\Pages\ListFactions;
use App\Filament\Resources\Factions\Schemas\FactionForm;
use App\Filament\Resources\Factions\Tables\FactionsTable;
use App\Models\Faction;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class FactionResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Operativos';
    protected static ?int $navigationSort = 3;

    protected static ?string $model = Faction::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Facción';

    protected static ?string $pluralModelLabel = 'Facciones';

    protected static ?string $navigationLabel = 'Facciones';

    public static function form(Schema $schema): Schema
    {
        return FactionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FactionsTable::configure($table);
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
            'index' => ListFactions::route('/'),
            'create' => CreateFaction::route('/create'),
            'edit' => EditFaction::route('/{record}/edit'),
        ];
    }
}
