<?php

namespace App\Filament\Resources\Countries;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\Countries\Pages\CreateCountry;
use App\Filament\Resources\Countries\Pages\EditCountry;
use App\Filament\Resources\Countries\Pages\ListCountries;
use App\Filament\Resources\Countries\Schemas\CountryForm;
use App\Filament\Resources\Countries\Tables\CountriesTable;
use App\Models\Country;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CountryResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Country::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedGlobeEuropeAfrica;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'País';

    protected static ?string $pluralModelLabel = 'Países';

    protected static ?string $navigationLabel = 'Países';

    public static function form(Schema $schema): Schema
    {
        return CountryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CountriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCountries::route('/'),
            'create' => CreateCountry::route('/create'),
            'edit' => EditCountry::route('/{record}/edit'),
        ];
    }
}
