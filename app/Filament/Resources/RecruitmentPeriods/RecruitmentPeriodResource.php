<?php

namespace App\Filament\Resources\RecruitmentPeriods;

use App\Filament\Resources\RecruitmentPeriods\Pages\EditRecruitmentPeriod;
use App\Filament\Resources\RecruitmentPeriods\Pages\ListRecruitmentPeriods;
use App\Filament\Resources\RecruitmentPeriods\RelationManagers\CommentsRelationManager;
use App\Filament\Resources\RecruitmentPeriods\Schemas\RecruitmentPeriodForm;
use App\Filament\Resources\RecruitmentPeriods\Tables\RecruitmentPeriodsTable;
use App\Models\RecruitmentPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;

class RecruitmentPeriodResource extends Resource
{
    protected static ?string $model = RecruitmentPeriod::class;
    protected static string|UnitEnum|null $navigationGroup = 'Reclutamiento';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAcademicCap;
    protected static ?string $navigationLabel = 'Área de tutores';
    protected static ?string $modelLabel = 'periodo de recluta';
    protected static ?string $pluralModelLabel = 'Área de tutores';
    protected static ?int $navigationSort = 1;

    public static function form(Schema $schema): Schema
    {
        return RecruitmentPeriodForm::configure($schema, false);
    }

    public static function table(Table $table): Table
    {
        return RecruitmentPeriodsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNull('ended_at')
            ->whereNotNull('open_user_id')
            ->with(['user.status', 'tutor', 'reinforcementAreas', 'promotionPendingBy'])
            ->withCount('comments');
    }

    public static function getRelations(): array
    {
        return [CommentsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListRecruitmentPeriods::route('/'),
            'edit' => EditRecruitmentPeriod::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }
}
