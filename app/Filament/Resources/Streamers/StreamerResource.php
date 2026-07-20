<?php

namespace App\Filament\Resources\Streamers;

use App\Filament\Resources\Streamers\Pages\CreateStreamer;
use App\Filament\Resources\Streamers\Pages\EditStreamer;
use App\Filament\Resources\Streamers\Pages\ListStreamers;
use App\Filament\Resources\Streamers\Schemas\StreamerForm;
use App\Filament\Resources\Streamers\Tables\StreamersTable;
use App\Models\Streamer;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class StreamerResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Streams';

    protected static ?int $navigationSort = 0;

    protected static ?string $model = Streamer::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $recordTitleAttribute = 'twich_channel';

    protected static ?string $modelLabel = 'Streamer';

    protected static ?string $pluralModelLabel = 'Streamers';

    protected static ?string $navigationLabel = 'Streamers';

    public static function form(Schema $schema): Schema
    {
        return StreamerForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StreamersTable::configure($table);
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
            'index' => ListStreamers::route('/'),
            'create' => CreateStreamer::route('/create'),
            'edit' => EditStreamer::route('/{record}/edit'),
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
