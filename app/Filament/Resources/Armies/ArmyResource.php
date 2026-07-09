<?php

namespace App\Filament\Resources\Armies;

use App\Filament\Resources\Armies\Pages\CreateArmy;
use App\Filament\Resources\Armies\Pages\EditArmy;
use App\Filament\Resources\Armies\Pages\ListArmies;
use App\Filament\Resources\Armies\Schemas\ArmyForm;
use App\Filament\Resources\Armies\Tables\ArmiesTable;
use App\Models\Army;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ArmyResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Configuración';

    protected static ?int $navigationSort = 6;

    protected static ?string $model = Army::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Ejército';

    protected static ?string $pluralModelLabel = 'Ejércitos';

    protected static ?string $navigationLabel = 'Ejércitos';

    public static function form(Schema $schema): Schema
    {
        return ArmyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ArmiesTable::configure($table);
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
            'index' => ListArmies::route('/'),
            'create' => CreateArmy::route('/create'),
            'edit' => EditArmy::route('/{record}/edit'),
        ];
    }
}
