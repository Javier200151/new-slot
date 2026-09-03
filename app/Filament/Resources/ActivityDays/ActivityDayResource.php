<?php

namespace App\Filament\Resources\ActivityDays;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\ActivityDays\Pages\CreateActivityDay;
use App\Filament\Resources\ActivityDays\Pages\EditActivityDay;
use App\Filament\Resources\ActivityDays\Pages\ListActivityDays;
use App\Filament\Resources\ActivityDays\Schemas\ActivityDayForm;
use App\Filament\Resources\ActivityDays\Tables\ActivityDaysTable;
use App\Models\ActivityDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ActivityDayResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static ?string $slug = 'activity-days';

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 7;
    protected static ?string $model = ActivityDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $recordTitleAttribute = 'name';
    
    protected static ?string $modelLabel = 'Día de actividad';

    protected static ?string $pluralModelLabel = 'Días de actividad';

    protected static ?string $navigationLabel = 'Días de actividad';

    public static function form(Schema $schema): Schema
    {
        return ActivityDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityDaysTable::configure($table);
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
            'index' => ListActivityDays::route('/'),
            'create' => CreateActivityDay::route('/create'),
            'edit' => EditActivityDay::route('/{record}/edit'),
        ];
    }
}
