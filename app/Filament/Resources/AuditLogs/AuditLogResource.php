<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ListAuditLogs;
use App\Filament\Resources\AuditLogs\Schemas\AuditLogForm;
use App\Filament\Resources\AuditLogs\Tables\AuditLogsTable;
use Spatie\Activitylog\Models\Activity;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use App\Filament\Resources\AuditLogs\Pages\ViewAuditLog;
use UnitEnum;

class AuditLogResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Sistema';
    protected static ?int $navigationSort = 1;

    protected static ?string $model = Activity::class;

    public static function canCreate(): bool
    {
        return false;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $navigationLabel = 'Auditoría';

    protected static ?string $modelLabel = 'registro de auditoría';

    protected static ?string $pluralModelLabel = 'Auditoría';

    protected static ?string $recordTitleAttribute = 'description';

    public static function form(Schema $schema): Schema
    {
        return AuditLogForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AuditLogsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAuditLogs::route('/'),
            'view' => ViewAuditLog::route('/{record}'),
        ];
    }
}