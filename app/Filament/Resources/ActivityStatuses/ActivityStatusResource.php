<?php

namespace App\Filament\Resources\ActivityStatuses;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\ActivityStatuses\Pages\CreateActivityStatus;
use App\Filament\Resources\ActivityStatuses\Pages\EditActivityStatus;
use App\Filament\Resources\ActivityStatuses\Pages\ListActivityStatuses;
use App\Filament\Resources\ActivityStatuses\Schemas\ActivityStatusForm;
use App\Filament\Resources\ActivityStatuses\Tables\ActivityStatusesTable;
use App\Models\ActivityStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ActivityStatusResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static ?string $slug = 'activity-statuses';

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 8;
    protected static ?string $model = ActivityStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Estado de actividad';

    protected static ?string $pluralModelLabel = 'Estados de actividad';

    protected static ?string $navigationLabel = 'Estados de actividad';

    public static function form(Schema $schema): Schema
    {
        return ActivityStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityStatusesTable::configure($table);
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
            'index' => ListActivityStatuses::route('/'),
            'create' => CreateActivityStatus::route('/create'),
            'edit' => EditActivityStatus::route('/{record}/edit'),
        ];
    }
}
