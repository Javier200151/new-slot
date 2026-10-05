<?php

namespace App\Filament\Resources\MemberProcedures;

use App\Filament\Resources\MemberProcedures\Pages\EditMemberProcedure;
use App\Filament\Resources\MemberProcedures\Pages\ListMemberProcedures;
use App\Filament\Resources\MemberProcedures\RelationManagers\StepsRelationManager;
use App\Filament\Resources\MemberProcedures\Schemas\MemberProcedureForm;
use App\Filament\Resources\MemberProcedures\Tables\MemberProceduresTable;
use App\Models\MemberProcedure;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class MemberProcedureResource extends Resource
{
    protected static ?string $model = MemberProcedure::class;
    protected static string|UnitEnum|null $navigationGroup = 'Procedimientos';
    protected static ?int $navigationSort = 1;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;
    protected static ?string $navigationLabel = 'Procedimientos';
    protected static ?string $modelLabel = 'Procedimiento';
    protected static ?string $pluralModelLabel = 'Procedimientos';
    protected static ?string $recordTitleAttribute = 'id';

    public static function form(Schema $schema): Schema
    {
        return MemberProcedureForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return MemberProceduresTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [StepsRelationManager::class];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMemberProcedures::route('/'),
            'edit' => EditMemberProcedure::route('/{record}/edit'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
