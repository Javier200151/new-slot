<?php

namespace App\Filament\Resources\SlotTypes;

use App\Filament\Clusters\EventConfiguration;
use App\Filament\Resources\SlotTypes\Pages\CreateSlotType;
use App\Filament\Resources\SlotTypes\Pages\EditSlotType;
use App\Filament\Resources\SlotTypes\Pages\ListSlotTypes;
use App\Filament\Resources\SlotTypes\RelationManagers\StatusesRelationManager;
use App\Filament\Resources\SlotTypes\Schemas\SlotTypeForm;
use App\Filament\Resources\SlotTypes\Tables\SlotTypesTable;
use App\Models\SlotType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class SlotTypeResource extends Resource
{
    protected static ?string $cluster = EventConfiguration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $model = SlotType::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Tipo de slot';

    protected static ?string $pluralModelLabel = 'Tipos de slot';

    protected static ?string $navigationLabel = 'Tipos de slot';

    public static function form(Schema $schema): Schema
    {
        return SlotTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SlotTypesTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            StatusesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListSlotTypes::route('/'),
            'create' => CreateSlotType::route('/create'),
            'edit' => EditSlotType::route('/{record}/edit'),
        ];
    }
}
