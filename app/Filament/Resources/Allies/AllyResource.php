<?php

namespace App\Filament\Resources\Allies;

use App\Filament\Clusters\EventConfiguration;
use App\Filament\Resources\Allies\Pages\CreateAlly;
use App\Filament\Resources\Allies\Pages\EditAlly;
use App\Filament\Resources\Allies\Pages\ListAllies;
use App\Filament\Resources\Allies\Schemas\AllyForm;
use App\Filament\Resources\Allies\Tables\AlliesTable;
use App\Models\Ally;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class AllyResource extends Resource
{
    protected static ?string $cluster = EventConfiguration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 5;

    protected static ?string $model = Ally::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedUsers;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Aliado';

    protected static ?string $pluralModelLabel = 'Aliados';

    protected static ?string $navigationLabel = 'Aliados';

    public static function form(Schema $schema): Schema
    {
        return AllyForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AlliesTable::configure($table);
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
            'index' => ListAllies::route('/'),
            'create' => CreateAlly::route('/create'),
            'edit' => EditAlly::route('/{record}/edit'),
        ];
    }
}
