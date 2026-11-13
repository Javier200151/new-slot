<?php

namespace App\Filament\Resources\CommunityRaffles;

use App\Filament\Resources\CommunityRaffles\Pages\CreateCommunityRaffle;
use App\Filament\Resources\CommunityRaffles\Pages\EditCommunityRaffle;
use App\Filament\Resources\CommunityRaffles\Pages\ListCommunityRaffles;
use App\Filament\Resources\CommunityRaffles\Schemas\CommunityRaffleForm;
use App\Filament\Resources\CommunityRaffles\Tables\CommunityRafflesTable;
use App\Models\CommunityRaffle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CommunityRaffleResource extends Resource
{
    protected static ?string $model = CommunityRaffle::class;
    protected static string|UnitEnum|null $navigationGroup = 'Comunidad';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-gift';
    protected static ?string $navigationLabel = 'Sorteos';
    protected static ?string $modelLabel = 'Sorteo';
    protected static ?string $pluralModelLabel = 'Sorteos';
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?int $navigationSort = 19;

    public static function form(Schema $schema): Schema
    {
        return CommunityRaffleForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityRafflesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityRaffles::route('/'),
            'create' => CreateCommunityRaffle::route('/create'),
            'edit' => EditCommunityRaffle::route('/{record}/edit'),
        ];
    }
}
