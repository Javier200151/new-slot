<?php

namespace App\Filament\Resources\Streams;

use App\Filament\Resources\Streams\Pages\CreateStream;
use App\Filament\Resources\Streams\Pages\EditStream;
use App\Filament\Resources\Streams\Pages\ListStreams;
use App\Filament\Resources\Streams\Schemas\StreamForm;
use App\Filament\Resources\Streams\Tables\StreamsTable;
use App\Models\Stream;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class StreamResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Streams';

    protected static ?int $navigationSort = 1;

    protected static ?string $model = Stream::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedVideoCamera;

    protected static ?string $recordTitleAttribute = 'stream_url';

    protected static ?string $modelLabel = 'Emisión';

    protected static ?string $pluralModelLabel = 'Emisiones';

    protected static ?string $navigationLabel = 'Emisiones';

    public static function form(Schema $schema): Schema
    {
        return StreamForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return StreamsTable::configure($table);
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
            'index' => ListStreams::route('/'),
            'create' => CreateStream::route('/create'),
            'edit' => EditStream::route('/{record}/edit'),
        ];
    }
}
