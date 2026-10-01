<?php

namespace App\Filament\Resources\InfrastructureSettings;

use App\Filament\Resources\InfrastructureSettings\Pages\EditInfrastructureSetting;
use App\Filament\Resources\InfrastructureSettings\Pages\ListInfrastructureSettings;
use App\Filament\Resources\InfrastructureSettings\Schemas\InfrastructureSettingForm;
use App\Filament\Resources\InfrastructureSettings\Tables\InfrastructureSettingsTable;
use App\Models\InfrastructureSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class InfrastructureSettingResource extends Resource
{
    protected static ?string $model = InfrastructureSetting::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedServerStack;
    protected static ?string $navigationLabel = 'Infraestructura';
    protected static ?string $modelLabel = 'Infraestructura';
    protected static ?string $pluralModelLabel = 'Infraestructura';

    public static function form(Schema $schema): Schema
    {
        return InfrastructureSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return InfrastructureSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListInfrastructureSettings::route('/'),
            'edit' => EditInfrastructureSetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
