<?php

namespace App\Filament\Resources\EventComments;

use App\Filament\Resources\EventComments\Pages\CreateEventComment;
use App\Filament\Resources\EventComments\Pages\EditEventComment;
use App\Filament\Resources\EventComments\Pages\ListEventComments;
use App\Filament\Resources\EventComments\Schemas\EventCommentForm;
use App\Filament\Resources\EventComments\Tables\EventCommentsTable;
use App\Models\EventComment;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;
use UnitEnum;

class EventCommentResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Eventos';

    protected static ?int $navigationSort = 2;

    protected static ?string $model = EventComment::class;

    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static ?string $recordTitleAttribute = 'comment';

    protected static ?string $modelLabel = 'Comentario evento';

    protected static ?string $pluralModelLabel = 'Comentarios evento';

    protected static ?string $navigationLabel = 'Comentarios evento';

    public static function form(Schema $schema): Schema
    {
        return EventCommentForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventCommentsTable::configure($table);
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
            'index' => ListEventComments::route('/'),
            'create' => CreateEventComment::route('/create'),
            'edit' => EditEventComment::route('/{record}/edit'),
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
