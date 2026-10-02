<?php

namespace App\Filament\Resources\VeterancySettings;

use App\Filament\Resources\VeterancySettings\Pages\EditVeterancySetting;
use App\Filament\Resources\VeterancySettings\Pages\ListVeterancySettings;
use App\Filament\Resources\VeterancySettings\Schemas\VeterancySettingForm;
use App\Filament\Resources\VeterancySettings\Tables\VeterancySettingsTable;
use App\Models\VeterancySetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class VeterancySettingResource extends Resource
{
    protected static ?string $model = VeterancySetting::class;
    protected static string|UnitEnum|null $navigationGroup = 'Usuarios';
    protected static ?int $navigationSort = 4;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedTrophy;
    protected static ?string $navigationLabel = 'Veteranías';
    protected static ?string $modelLabel = 'Configuración de veteranías';
    protected static ?string $pluralModelLabel = 'Veteranías';

    public static function form(Schema $schema): Schema
    {
        return VeterancySettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return VeterancySettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListVeterancySettings::route('/'),
            'edit' => EditVeterancySetting::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
