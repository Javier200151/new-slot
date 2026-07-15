<?php

namespace App\Filament\Resources\RadioModels;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\RadioModels\Pages\CreateRadioModel;
use App\Filament\Resources\RadioModels\Pages\EditRadioModel;
use App\Filament\Resources\RadioModels\Pages\ListRadioModels;
use App\Filament\Resources\RadioModels\Schemas\RadioModelForm;
use App\Filament\Resources\RadioModels\Tables\RadioModelsTable;
use App\Models\RadioModel;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class RadioModelResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 12;

    protected static ?string $model = RadioModel::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedSignal;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Modelo de radio';

    protected static ?string $pluralModelLabel = 'Modelos de radio';

    protected static ?string $navigationLabel = 'Modelos de radio';

    public static function form(Schema $schema): Schema
    {
        return RadioModelForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return RadioModelsTable::configure($table);
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
            'index' => ListRadioModels::route('/'),
            'create' => CreateRadioModel::route('/create'),
            'edit' => EditRadioModel::route('/{record}/edit'),
        ];
    }
}
