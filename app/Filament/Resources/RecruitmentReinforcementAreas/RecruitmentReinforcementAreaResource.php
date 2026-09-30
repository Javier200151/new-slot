<?php
namespace App\Filament\Resources\RecruitmentReinforcementAreas;

use App\Filament\Resources\RecruitmentReinforcementAreas\Pages\CreateRecruitmentReinforcementArea;
use App\Filament\Resources\RecruitmentReinforcementAreas\Pages\EditRecruitmentReinforcementArea;
use App\Filament\Resources\RecruitmentReinforcementAreas\Pages\ListRecruitmentReinforcementAreas;
use App\Filament\Resources\RecruitmentReinforcementAreas\Schemas\RecruitmentReinforcementAreaForm;
use App\Filament\Resources\RecruitmentReinforcementAreas\Tables\RecruitmentReinforcementAreasTable;
use App\Models\RecruitmentReinforcementArea;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RecruitmentReinforcementAreaResource extends Resource
{
    protected static ?string $model = RecruitmentReinforcementArea::class;
    protected static string|UnitEnum|null $navigationGroup = 'Tutores';
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedWrenchScrewdriver;
    protected static ?string $navigationLabel = 'Áreas de refuerzo';
    protected static ?string $modelLabel = 'área de refuerzo';
    protected static ?string $pluralModelLabel = 'Áreas de refuerzo';
    protected static ?int $navigationSort = 4;
    public static function form(Schema $schema): Schema { return RecruitmentReinforcementAreaForm::configure($schema); }
    public static function table(Table $table): Table { return RecruitmentReinforcementAreasTable::configure($table); }
    public static function getPages(): array { return [
        'index' => ListRecruitmentReinforcementAreas::route('/'),
        'create' => CreateRecruitmentReinforcementArea::route('/create'),
        'edit' => EditRecruitmentReinforcementArea::route('/{record}/edit'),
    ]; }
}
