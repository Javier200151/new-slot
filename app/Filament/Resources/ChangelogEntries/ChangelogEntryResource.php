<?php

namespace App\Filament\Resources\ChangelogEntries;

use App\Filament\Resources\ChangelogEntries\Pages\CreateChangelogEntry;
use App\Filament\Resources\ChangelogEntries\Pages\EditChangelogEntry;
use App\Filament\Resources\ChangelogEntries\Pages\ListChangelogEntries;
use App\Filament\Resources\ChangelogEntries\Schemas\ChangelogEntryForm;
use App\Filament\Resources\ChangelogEntries\Tables\ChangelogEntriesTable;
use App\Models\ChangelogEntry;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class ChangelogEntryResource extends Resource
{
    protected static ?string $model = ChangelogEntry::class;
    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 3;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentText;
    protected static ?string $navigationLabel = 'Changelog';
    protected static ?string $modelLabel = 'Entrada de changelog';
    protected static ?string $pluralModelLabel = 'Changelog';
    protected static ?string $recordTitleAttribute = 'version';

    public static function form(Schema $schema): Schema
    {
        return ChangelogEntryForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ChangelogEntriesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListChangelogEntries::route('/'),
            'create' => CreateChangelogEntry::route('/create'),
            'edit' => EditChangelogEntry::route('/{record}/edit'),
        ];
    }
}
