<?php

namespace App\Filament\Resources\CommunityProcesses;

use App\Filament\Resources\CommunityProcesses\Pages\CreateCommunityProcess;
use App\Filament\Resources\CommunityProcesses\Pages\EditCommunityProcess;
use App\Filament\Resources\CommunityProcesses\Pages\ListCommunityProcesses;
use App\Filament\Resources\CommunityProcesses\Schemas\CommunityProcessForm;
use App\Filament\Resources\CommunityProcesses\Tables\CommunityProcessesTable;
use App\Models\CommunityProcess;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CommunityProcessResource extends Resource
{
    protected static ?string $model = CommunityProcess::class;
    protected static string|UnitEnum|null $navigationGroup = 'Comunidad';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';
    protected static ?string $navigationLabel = 'Procesos comunitarios';
    protected static ?string $modelLabel = 'Proceso comunitario';
    protected static ?string $pluralModelLabel = 'Procesos comunitarios';
    protected static ?int $navigationSort = 1;

    public static function shouldRegisterNavigation(): bool
    {
        // El flujo normal vive en Área 51 > Personal. Conservamos el Resource
        // únicamente como herramienta administrativa de emergencia.
        return false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return CommunityProcessForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityProcessesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityProcesses::route('/'),
            'create' => CreateCommunityProcess::route('/create'),
            'edit' => EditCommunityProcess::route('/{record}/edit'),
        ];
    }
}
