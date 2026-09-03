<?php

namespace App\Filament\Resources\ActivityTypes;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\ActivityTypes\Pages\CreateActivityType;
use App\Filament\Resources\ActivityTypes\Pages\EditActivityType;
use App\Filament\Resources\ActivityTypes\Pages\ListActivityTypes;
use App\Filament\Resources\ActivityTypes\Schemas\ActivityTypeForm;
use App\Filament\Resources\ActivityTypes\Tables\ActivityTypesTable;
use App\Models\ActivityType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ActivityTypeResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static ?string $slug = 'activity-types';

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 4;
    protected static ?string $model = ActivityType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Tipo de actividad';

    protected static ?string $pluralModelLabel = 'Tipos de actividad';

    protected static ?string $navigationLabel = 'Tipos de actividad';

    public static function form(Schema $schema): Schema
    {
        return ActivityTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityTypesTable::configure($table);
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
            'index' => ListActivityTypes::route('/'),
            'create' => CreateActivityType::route('/create'),
            'edit' => EditActivityType::route('/{record}/edit'),
        ];
    }
}
