<?php
namespace App\Filament\Resources\RecruitmentHistory;

use App\Filament\Resources\RecruitmentHistory\Pages\ListRecruitmentHistory;
use App\Filament\Resources\RecruitmentHistory\Pages\ViewRecruitmentHistory;
use App\Filament\Resources\RecruitmentHistory\Schemas\RecruitmentHistoryForm;
use App\Filament\Resources\RecruitmentHistory\Tables\RecruitmentHistoryTable;
use App\Filament\Resources\RecruitmentPeriods\RelationManagers\CommentsRelationManager;
use App\Models\RecruitmentPeriod;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use UnitEnum;
class RecruitmentHistoryResource extends Resource
{
    protected static ?string $model=RecruitmentPeriod::class;
    protected static string|UnitEnum|null $navigationGroup='Reclutamiento';
    protected static string|BackedEnum|null $navigationIcon=Heroicon::OutlinedArchiveBox;
    protected static ?string $navigationLabel='Histórico de reclutas';
    protected static ?string $modelLabel='periodo histórico';
    protected static ?string $pluralModelLabel='Histórico de reclutas';
    protected static ?int $navigationSort=2;
    public static function form(Schema $schema): Schema { return RecruitmentHistoryForm::configure($schema); }
    public static function table(Table $table): Table { return RecruitmentHistoryTable::configure($table); }
    public static function getEloquentQuery(): Builder { return parent::getEloquentQuery()->whereNotNull('ended_at')->with(['user','tutor','reinforcementAreas','finalStatus','promotionPendingBy'])->withCount('comments'); }
    public static function getRelations(): array { return [CommentsRelationManager::class]; }
    public static function getPages(): array { return ['index'=>ListRecruitmentHistory::route('/'),'view'=>ViewRecruitmentHistory::route('/{record}')]; }
    public static function canCreate(): bool { return false; }
}
