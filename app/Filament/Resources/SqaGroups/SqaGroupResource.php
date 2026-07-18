<?php

namespace App\Filament\Resources\SqaGroups;

use App\Filament\Resources\SqaGroups\Pages\CreateSqaGroup;
use App\Filament\Resources\SqaGroups\Pages\EditSqaGroup;
use App\Filament\Resources\SqaGroups\Pages\ListSqaGroups;
use App\Filament\Resources\SqaGroups\Schemas\SqaGroupForm;
use App\Filament\Resources\SqaGroups\Tables\SqaGroupsTable;
use App\Models\SqaGroup;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class SqaGroupResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Usuarios';

    protected static ?int $navigationSort = 5;

    protected static ?string $model = SqaGroup::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Grupo SQA';

    protected static ?string $pluralModelLabel = 'Grupos SQA';

    protected static ?string $navigationLabel = 'Grupos SQA';

    public static function form(Schema $schema): Schema
    {
        return SqaGroupForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SqaGroupsTable::configure($table);
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
            'index' => ListSqaGroups::route('/'),
            'create' => CreateSqaGroup::route('/create'),
            'edit' => EditSqaGroup::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
