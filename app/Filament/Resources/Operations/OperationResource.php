<?php

namespace App\Filament\Resources\Operations;

use App\Filament\Resources\Operations\Pages\CreateOperation;
use App\Filament\Resources\Operations\Pages\EditOperation;
use App\Filament\Resources\Operations\Pages\ListOperations;
use App\Filament\Resources\Operations\Schemas\OperationForm;
use App\Filament\Resources\Operations\Tables\OperationsTable;
use App\Models\Operation;
use App\Support\OperationTypeAccess;
use Illuminate\Support\Facades\Auth;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class OperationResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Operativos';
    protected static ?int $navigationSort = 1;
    protected static ?string $model = Operation::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboard;

    protected static ?string $recordTitleAttribute = 'Operativo';
    protected static ?string $pluralModelLabel = 'Operativos';

    protected static ?string $navigationLabel = 'Operativos';

    public static function form(Schema $schema): Schema
    {
        return OperationForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return OperationsTable::configure($table);
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
            'index' => ListOperations::route('/'),
            'create' => CreateOperation::route('/create'),
            'edit' => EditOperation::route('/{record}/edit'),
        ];
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = Auth::user();
        $allowedTypeIds = OperationTypeAccess::allowedTypeIds(
            $user,
            'operations',
            'view',
        );

        if ($allowedTypeIds === []) {
            return $query->whereRaw('1 = 0');
        }

        return $query->whereIn(
            'operation_type_id',
            $allowedTypeIds,
        );
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    public static function getNavigationBadge(): ?string
    {
        return (string) static::getEloquentQuery()->count();
    }
}
