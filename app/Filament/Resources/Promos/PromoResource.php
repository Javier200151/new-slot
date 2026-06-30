<?php

namespace App\Filament\Resources\Promos;

use App\Filament\Resources\Promos\Pages\CreatePromo;
use App\Filament\Resources\Promos\Pages\EditPromo;
use App\Filament\Resources\Promos\Pages\ListPromos;
use App\Filament\Resources\Promos\Schemas\PromoForm;
use App\Filament\Resources\Promos\Tables\PromosTable;
use App\Models\Promo;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class PromoResource extends Resource
{
    protected static ?string $model = Promo::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::BuildingLibrary; //OutlinedRectangleStack;

    protected static string | UnitEnum | null $navigationGroup = 'Usuarios'; //Grupo
    protected static ?int $navigationSort = 3; // Orden en el grupo

    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return PromoForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return PromosTable::configure($table);
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
            'index' => ListPromos::route('/'),
            'create' => CreatePromo::route('/create'),
            'edit' => EditPromo::route('/{record}/edit'),
        ];
    }
}
