<?php

namespace App\Filament\Resources\EventStatuses;

use App\Filament\Resources\EventStatuses\Pages\CreateEventStatus;
use App\Filament\Resources\EventStatuses\Pages\EditEventStatus;
use App\Filament\Resources\EventStatuses\Pages\ListEventStatuses;
use App\Filament\Resources\EventStatuses\Schemas\EventStatusForm;
use App\Filament\Resources\EventStatuses\Tables\EventStatusesTable;
use App\Models\EventStatus;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class EventStatusResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Eventos';

    protected static ?int $navigationSort = 3;

    protected static ?string $model = EventStatus::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedCheckCircle;

    protected static ?string $recordTitleAttribute = 'name';

    protected static ?string $modelLabel = 'Estado de evento';

    protected static ?string $pluralModelLabel = 'Estados de evento';

    protected static ?string $navigationLabel = 'Estados de evento';

    public static function form(Schema $schema): Schema
    {
        return EventStatusForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventStatusesTable::configure($table);
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
            'index' => ListEventStatuses::route('/'),
            'create' => CreateEventStatus::route('/create'),
            'edit' => EditEventStatus::route('/{record}/edit'),
        ];
    }
}
