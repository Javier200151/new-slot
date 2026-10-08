<?php

namespace App\Filament\Resources\TreasurySettings;

use App\Filament\Resources\TreasurySettings\Pages\EditTreasurySetting;
use App\Filament\Resources\TreasurySettings\Pages\ListTreasurySettings;
use App\Filament\Resources\TreasurySettings\Schemas\TreasurySettingForm;
use App\Filament\Resources\TreasurySettings\Tables\TreasurySettingsTable;
use App\Models\MemberProcedureSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class TreasurySettingResource extends Resource
{
    protected static ?string $model = MemberProcedureSetting::class;

    protected static string|UnitEnum|null $navigationGroup = 'Tesorería';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBanknotes;

    protected static ?string $navigationLabel = 'Panel de Tesorería';

    protected static ?string $modelLabel = 'Configuración de Tesorería';

    protected static ?string $pluralModelLabel = 'Configuración de Tesorería';

    public static function form(Schema $schema): Schema
    {
        return TreasurySettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TreasurySettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTreasurySettings::route('/'),
            'edit' => EditTreasurySetting::route('/{record}/edit'),
        ];
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->can('treasury-settings.view') ?? false;
    }

    public static function canView(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return auth()->user()?->can('treasury-settings.update') ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
