<?php

namespace App\Filament\Resources\OperationTypes;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\OperationTypes\Pages\CreateOperationType;
use App\Filament\Resources\OperationTypes\Pages\EditOperationType;
use App\Filament\Resources\OperationTypes\Pages\ListOperationTypes;
use App\Filament\Resources\OperationTypes\Schemas\OperationTypeForm;
use App\Filament\Resources\OperationTypes\Tables\OperationTypesTable;
use App\Models\OperationType;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OperationTypeResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 4;
    protected static ?string $model = OperationType::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Tipo de actividad';

    protected static ?string $pluralModelLabel = 'Tipos de actividad';

    protected static ?string $navigationLabel = 'Tipos de actividad';

    public static function form(Schema $schema): Schema
    {
        return OperationTypeForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationTypesTable::configure($table);
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
            'index' => ListOperationTypes::route('/'),
            'create' => CreateOperationType::route('/create'),
            'edit' => EditOperationType::route('/{record}/edit'),
        ];
    }
}
