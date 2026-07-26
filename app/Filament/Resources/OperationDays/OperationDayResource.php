<?php

namespace App\Filament\Resources\OperationDays;

use App\Filament\Clusters\Configuration;
use App\Filament\Resources\OperationDays\Pages\CreateOperationDay;
use App\Filament\Resources\OperationDays\Pages\EditOperationDay;
use App\Filament\Resources\OperationDays\Pages\ListOperationDays;
use App\Filament\Resources\OperationDays\Schemas\OperationDayForm;
use App\Filament\Resources\OperationDays\Tables\OperationDaysTable;
use App\Models\OperationDay;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class OperationDayResource extends Resource
{
    protected static ?string $cluster = Configuration::class;

    protected static string | UnitEnum | null $navigationGroup = null;
    protected static ?int $navigationSort = 7;
    protected static ?string $model = OperationDay::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDateRange;

    protected static ?string $recordTitleAttribute = 'Día operativo';
    
    protected static ?string $pluralModelLabel = 'Días operativo';

    protected static ?string $navigationLabel = 'Días operativo';

    public static function form(Schema $schema): Schema
    {
        return OperationDayForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationDaysTable::configure($table);
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
            'index' => ListOperationDays::route('/'),
            'create' => CreateOperationDay::route('/create'),
            'edit' => EditOperationDay::route('/{record}/edit'),
        ];
    }
}
