<?php

namespace App\Filament\Resources\AddonPresets;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\AddonPresets\Pages\CreateAddonPreset;
use App\Filament\Resources\AddonPresets\Pages\EditAddonPreset;
use App\Filament\Resources\AddonPresets\Pages\ListAddonPresets;
use App\Filament\Resources\AddonPresets\Schemas\AddonPresetForm;
use App\Filament\Resources\AddonPresets\Tables\AddonPresetsTable;
use App\Models\AddonPreset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AddonPresetResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 11;

    protected static ?string $model = AddonPreset::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedPuzzlePiece;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Preset de addons';

    protected static ?string $pluralModelLabel = 'Presets de addons';

    protected static ?string $navigationLabel = 'Presets de addons';

    public static function form(Schema $schema): Schema
    {
        return AddonPresetForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AddonPresetsTable::configure($table);
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
            'index' => ListAddonPresets::route('/'),
            'create' => CreateAddonPreset::route('/create'),
            'edit' => EditAddonPreset::route('/{record}/edit'),
        ];
    }
}
