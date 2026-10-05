<?php

namespace App\Filament\Resources\MemberProcedureSettings;

use App\Filament\Resources\MemberProcedureSettings\Pages\EditMemberProcedureSetting;
use App\Filament\Resources\MemberProcedureSettings\Pages\ListMemberProcedureSettings;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\DepartureStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\DismissalStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\ReactivationStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\RecruitmentCompleteStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\RecruitmentStartStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\RelationManagers\ReserveStepsRelationManager;
use App\Filament\Resources\MemberProcedureSettings\Schemas\MemberProcedureSettingForm;
use App\Filament\Resources\MemberProcedureSettings\Tables\MemberProcedureSettingsTable;
use App\Models\MemberProcedureSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MemberProcedureSettingResource extends Resource
{
    protected static ?string $model = MemberProcedureSetting::class;
    protected static string|UnitEnum|null $navigationGroup = 'Procedimientos';
    protected static ?int $navigationSort = 2;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedAdjustmentsHorizontal;
    protected static ?string $navigationLabel = 'Config. procedimientos';
    protected static ?string $modelLabel = 'Configuración de procedimientos';
    protected static ?string $pluralModelLabel = 'Configuración de procedimientos';

    public static function form(Schema $schema): Schema
    {
        return MemberProcedureSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProcedureSettingsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            RecruitmentStartStepsRelationManager::class,
            RecruitmentCompleteStepsRelationManager::class,
            ReserveStepsRelationManager::class,
            ReactivationStepsRelationManager::class,
            DepartureStepsRelationManager::class,
            DismissalStepsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberProcedureSettings::route('/'),
            'edit' => EditMemberProcedureSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
