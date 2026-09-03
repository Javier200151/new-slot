<?php

namespace App\Filament\Resources\Activities;

use App\Filament\Resources\Activities\Pages\CreateActivity;
use App\Filament\Resources\Activities\Pages\EditActivity;
use App\Filament\Resources\Activities\Pages\ListActivities;
use App\Filament\Resources\Activities\Schemas\ActivityForm;
use App\Filament\Resources\Activities\Tables\ActivitiesTable;
use App\Models\Activity;
use App\Support\ActivityTypeAccess;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class ActivityResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Actividades';
    protected static ?int $navigationSort = 1;
    protected static ?string $model = Activity::class;

    protected static ?string $slug = 'activities';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboard;

    protected static ?string $recordTitleAttribute = 'name';
    protected static ?string $modelLabel = 'Actividad';
    protected static ?string $pluralModelLabel = 'Actividades';

    protected static ?string $navigationLabel = 'Actividades';

    public static function form(Schema $schema): Schema
    {
        return ActivityForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitiesTable::configure($table);
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
            'index' => ListActivities::route('/'),
            'create' => CreateActivity::route('/create'),
            'edit' => EditActivity::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $allowedTypeIds = ActivityTypeAccess::allowedTypeIds(
            $user,
            'activities',
            'view',
        );

        if ($allowedTypeIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'activity_type_id',
            $allowedTypeIds,
        );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }
}
