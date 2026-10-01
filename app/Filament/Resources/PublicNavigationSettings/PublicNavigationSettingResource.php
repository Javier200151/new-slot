<?php

namespace App\Filament\Resources\PublicNavigationSettings;

use App\Filament\Resources\PublicNavigationSettings\Pages\CreatePublicNavigationSetting;
use App\Filament\Resources\PublicNavigationSettings\Pages\EditPublicNavigationSetting;
use App\Filament\Resources\PublicNavigationSettings\Pages\ListPublicNavigationSettings;
use App\Filament\Resources\PublicNavigationSettings\Schemas\PublicNavigationSettingForm;
use App\Filament\Resources\PublicNavigationSettings\Tables\PublicNavigationSettingsTable;
use App\Models\PublicNavigationSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PublicNavigationSettingResource extends Resource
{
    protected static ?string $model = PublicNavigationSetting::class;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';

    protected static ?int $navigationSort = 1;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedBars3;

    protected static ?string $navigationLabel = 'Navegación pública';

    protected static ?string $modelLabel = 'Navegación pública';

    protected static ?string $pluralModelLabel = 'Navegación pública';

    public static function form(Schema $schema): Schema
    {
        return PublicNavigationSettingForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PublicNavigationSettingsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPublicNavigationSettings::route('/'),
            'create' => CreatePublicNavigationSetting::route('/create'),
            'edit' => EditPublicNavigationSetting::route('/{record}/edit'),
        ];
    }
}
