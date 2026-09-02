<?php

namespace App\Filament\Resources\CommunityPolls;

use App\Filament\Resources\CommunityPolls\Pages\CreateCommunityPoll;
use App\Filament\Resources\CommunityPolls\Pages\EditCommunityPoll;
use App\Filament\Resources\CommunityPolls\Pages\ListCommunityPolls;
use App\Filament\Resources\CommunityPolls\Schemas\CommunityPollForm;
use App\Filament\Resources\CommunityPolls\Tables\CommunityPollsTable;
use App\Models\CommunityPoll;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use UnitEnum;

class CommunityPollResource extends Resource
{
    protected static ?string $model = CommunityPoll::class;

    protected static string|UnitEnum|null $navigationGroup = 'Comunidad';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChartBarSquare;

    protected static ?string $navigationLabel = 'Votaciones';

    protected static ?string $modelLabel = 'Votación';

    protected static ?string $pluralModelLabel = 'Votaciones';

    protected static ?int $navigationSort = 2;

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
        return CommunityPollForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityPollsTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityPolls::route('/'),
            'create' => CreateCommunityPoll::route('/create'),
            'edit' => EditCommunityPoll::route('/{record}/edit'),
        ];
    }
}
