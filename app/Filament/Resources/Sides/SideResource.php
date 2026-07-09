<?php

namespace App\Filament\Resources\Sides;

use App\Filament\Resources\Sides\Pages\CreateSide;
use App\Filament\Resources\Sides\Pages\EditSide;
use App\Filament\Resources\Sides\Pages\ListSides;
use App\Filament\Resources\Sides\Schemas\SideForm;
use App\Filament\Resources\Sides\Tables\SidesTable;
use App\Models\Side;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class SideResource extends Resource
{
    protected static ?string $model = Side::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'Bando';

    public static function form(Schema $schema): Schema
    {
        return SideForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return SidesTable::configure($table);
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
            'index' => ListSides::route('/'),
            'create' => CreateSide::route('/create'),
            'edit' => EditSide::route('/{record}/edit'),
        ];
    }
}
