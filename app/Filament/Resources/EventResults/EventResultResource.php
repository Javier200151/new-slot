<?php

namespace App\Filament\Resources\EventResults;

use App\Filament\Clusters\EventConfiguration;
use App\Filament\Resources\EventResults\Pages\CreateEventResult;
use App\Filament\Resources\EventResults\Pages\EditEventResult;
use App\Filament\Resources\EventResults\Pages\ListEventResults;
use App\Filament\Resources\EventResults\Schemas\EventResultForm;
use App\Filament\Resources\EventResults\Tables\EventResultsTable;
use App\Models\EventResult;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EventResultResource extends Resource
{
    protected static ?string $cluster = EventConfiguration::class;

    protected static string | UnitEnum | null $navigationGroup = null;

    protected static ?int $navigationSort = 4;

    protected static ?string $model = EventResult::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedTrophy;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Resultado de evento';

    protected static ?string $pluralModelLabel = 'Resultados de evento';

    protected static ?string $navigationLabel = 'Resultados de evento';

    public static function form(Schema $schema): Schema
    {
        return EventResultForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventResultsTable::configure($table);
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
            'index' => ListEventResults::route('/'),
            'create' => CreateEventResult::route('/create'),
            'edit' => EditEventResult::route('/{record}/edit'),
        ];
    }
}
