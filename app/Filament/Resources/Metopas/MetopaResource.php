<?php

namespace App\Filament\Resources\Metopas;

use App\Filament\Resources\Metopas\Pages\CreateMetopa;
use App\Filament\Resources\Metopas\Pages\EditMetopa;
use App\Filament\Resources\Metopas\Pages\ListMetopas;
use App\Filament\Resources\Metopas\Schemas\MetopaForm;
use App\Filament\Resources\Metopas\Tables\MetopasTable;
use App\Models\Metopa;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use App\Filament\Resources\Metopas\RelationManagers\UsersRelationManager;
use UnitEnum;

class MetopaResource extends Resource
{
    protected static ?string $modelLabel = 'Metopa';
    protected static ?string $pluralModelLabel = 'Metopas';
    protected static ?string $navigationLabel = 'Metopas';

    protected static ?string $model = Metopa::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::Trophy; //OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Usuarios'; //Grupo
    protected static ?int $navigationSort = 2; // Orden en el grupo

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return MetopaForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MetopasTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            UsersRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMetopas::route('/'),
            'create' => CreateMetopa::route('/create'),
            'edit' => EditMetopa::route('/{record}/edit'),
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
