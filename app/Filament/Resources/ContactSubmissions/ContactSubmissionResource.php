<?php

namespace App\Filament\Resources\ContactSubmissions;

use App\Filament\Resources\ContactSubmissions\Pages\EditContactSubmission;
use App\Filament\Resources\ContactSubmissions\Pages\ListContactSubmissions;
use App\Filament\Resources\ContactSubmissions\Schemas\ContactSubmissionForm;
use App\Filament\Resources\ContactSubmissions\Tables\ContactSubmissionsTable;
use App\Models\ContactSubmission;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ContactSubmissionResource extends Resource
{
    protected static ?string $model = ContactSubmission::class;

    protected static string|UnitEnum|null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 5;
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedInbox;
    protected static ?string $navigationLabel = 'Contacto';
    protected static ?string $modelLabel = 'Consulta';
    protected static ?string $pluralModelLabel = 'Consultas';

    public static function form(Schema $schema): Schema
    {
        return ContactSubmissionForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ContactSubmissionsTable::configure($table);
    }

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->where('is_recruitment', false);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactSubmissions::route('/'),
            'edit' => EditContactSubmission::route('/{record}/edit'),
        ];
    }

    public static function getNavigationBadge(): ?string
    {
        if (! static::canViewAny()) {
            return null;
        }

        return (string) static::getEloquentQuery()
            ->whereNull('read_at')
            ->count();
    }

    public static function canView(Model $record): bool
    {
        return ! (bool) $record->is_recruitment && parent::canView($record);
    }

    public static function canEdit(Model $record): bool
    {
        return ! (bool) $record->is_recruitment && parent::canEdit($record);
    }

    public static function canCreate(): bool
    {
        return false;
    }
}
