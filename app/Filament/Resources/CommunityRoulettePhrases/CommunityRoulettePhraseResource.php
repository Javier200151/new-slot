<?php

namespace App\Filament\Resources\CommunityRoulettePhrases;

use App\Filament\Resources\CommunityRoulettePhrases\Pages\CreateCommunityRoulettePhrase;
use App\Filament\Resources\CommunityRoulettePhrases\Pages\EditCommunityRoulettePhrase;
use App\Filament\Resources\CommunityRoulettePhrases\Pages\ListCommunityRoulettePhrases;
use App\Filament\Resources\CommunityRoulettePhrases\Schemas\CommunityRoulettePhraseForm;
use App\Filament\Resources\CommunityRoulettePhrases\Tables\CommunityRoulettePhrasesTable;
use App\Models\CommunityRoulettePhrase;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use UnitEnum;

class CommunityRoulettePhraseResource extends Resource
{
    protected static ?string $model = CommunityRoulettePhrase::class;

    protected static string|UnitEnum|null $navigationGroup = 'Comunidad';
    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-sparkles';
    protected static ?string $navigationLabel = 'Frases de la ruleta';
    protected static ?string $modelLabel = 'Frase de la ruleta';
    protected static ?string $pluralModelLabel = 'Frases de la ruleta';
    protected static ?string $recordTitleAttribute = 'text';
    protected static ?int $navigationSort = 20;

    public static function form(Schema $schema): Schema
    {
        return CommunityRoulettePhraseForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return CommunityRoulettePhrasesTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityRoulettePhrases::route('/'),
            'create' => CreateCommunityRoulettePhrase::route('/create'),
            'edit' => EditCommunityRoulettePhrase::route('/{record}/edit'),
        ];
    }
}
