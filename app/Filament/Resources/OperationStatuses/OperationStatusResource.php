<?php

namespace App\Filament\Resources\OperationStatuses;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\OperationStatuses\Pages\CreateOperationStatus;
use App\Filament\Resources\OperationStatuses\Pages\EditOperationStatus;
use App\Filament\Resources\OperationStatuses\Pages\ListOperationStatuses;
use App\Filament\Resources\OperationStatuses\Schemas\OperationStatusForm;
use App\Filament\Resources\OperationStatuses\Tables\OperationStatusesTable;
use App\Models\OperationStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OperationStatusResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 3;
    protected static ?string $model = OperationStatus::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Estado operativo';

    protected static ?string $pluralModelLabel = 'Estados operativo';

    protected static ?string $navigationLabel = 'Estados operativo';

    public static function form(Schema $schema): Schema
    {
        return OperationStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationStatusesTable::configure($table);
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
            'index' => ListOperationStatuses::route('/'),
            'create' => CreateOperationStatus::route('/create'),
            'edit' => EditOperationStatus::route('/{record}/edit'),
        ];
    }
}
