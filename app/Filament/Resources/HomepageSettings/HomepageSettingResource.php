<?php

namespace App\Filament\Resources\HomepageSettings;

use App\Filament\Resources\HomepageSettings\Pages\CreateHomepageSetting;
use App\Filament\Resources\HomepageSettings\Pages\EditHomepageSetting;
use App\Filament\Resources\HomepageSettings\Pages\ListHomepageSettings;
use App\Filament\Resources\HomepageSettings\Schemas\HomepageSettingForm;
use App\Filament\Resources\HomepageSettings\Tables\HomepageSettingsTable;
use App\Models\HomepageSetting;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class HomepageSettingResource extends Resource
{
    protected static ?string $model = HomepageSetting::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 0;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedHomeModern;
    protected static ?string $navigationLabel = 'Portada';
    protected static ?string $modelLabel = 'Configuración de portada';
    protected static ?string $pluralModelLabel = 'Configuración de portada';

    public static function form(Schema $schema): Schema { return HomepageSettingForm::configure($schema); }
    public static function table(Table $table): Table { return HomepageSettingsTable::configure($table); }
    public static function getPages(): array { return ['index'=>ListHomepageSettings::route('/'),'create'=>CreateHomepageSetting::route('/create'),'edit'=>EditHomepageSetting::route('/{record}/edit')]; }
}
