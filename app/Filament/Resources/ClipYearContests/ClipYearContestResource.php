<?php

namespace App\Filament\Resources\ClipYearContests;

use App\Filament\Resources\ClipYearContests\Pages\ListClipYearContest;
use App\Filament\Resources\ClipYearContests\Pages\CreateClipYearContest;
use App\Filament\Resources\ClipYearContests\Pages\EditClipYearContest;
use App\Models\ClipYearContest;
use App\Models\Status;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\DateTimePicker;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Actions\EditAction;
use BackedEnum;
use UnitEnum;

class ClipYearContestResource extends Resource
{
    protected static ?string $model = ClipYearContest::class;
    protected static string | UnitEnum | null $navigationGroup = 'Streams';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-trophy';
    protected static ?string $navigationLabel = 'Clip del año · Ediciones';
    protected static ?string $modelLabel = 'Edición del clip del año';
    protected static ?string $pluralModelLabel = 'Clip del año';
    public static function canViewAny(): bool { return (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('clip-contest.manage')); }
    public static function canCreate(): bool { return self::canViewAny(); }
    public static function canEdit($record): bool { return self::canViewAny(); }
    public static function canDelete($record): bool { return false; }
    public static function form(Schema $schema): Schema
    {
        $year = now()->year;
        return $schema->components([
            TextInput::make('year')->label('Año')->numeric()->minValue(2026)->maxValue(2200)->required()->unique(ignoreRecord:true)->default($year),
            Toggle::make('enabled')->label('Habilitar concurso')->default(false),

            TextInput::make('tab_text_nominations')
                ->label('Texto de la pestaña · Candidaturas')
                ->helperText('Se muestra en la pestaña lateral durante el plazo para presentar clips.')
                ->default(ClipYearContest::DEFAULT_TAB_NOMINATIONS)
                ->formatStateUsing(fn (?string $state): string => $state ?? ClipYearContest::DEFAULT_TAB_NOMINATIONS)
                ->required()
                ->maxLength(120),
            TextInput::make('tab_text_voting')
                ->label('Texto de la pestaña · Votación')
                ->helperText('Se muestra en la misma pestaña durante el plazo de votación.')
                ->default(ClipYearContest::DEFAULT_TAB_VOTING)
                ->formatStateUsing(fn (?string $state): string => $state ?? ClipYearContest::DEFAULT_TAB_VOTING)
                ->required()
                ->maxLength(120),
            Textarea::make('rules_text')
                ->label('Bases y condiciones del concurso')
                ->helperText('Aparecerán en la página pública de Clip del año. Usa saltos de línea para separar apartados.')
                ->rows(14)
                ->columnSpanFull()
                ->default(ClipYearContest::defaultRules())
                ->formatStateUsing(fn (?string $state): string => $state ?? ClipYearContest::defaultRules())
                ->required()
                ->maxLength(8000),
            Select::make('allowed_status_ids')->label('Estados que pueden participar')
                ->options(fn (): array => Status::query()->orderBy('name')->pluck('name', 'id')->all())
                ->multiple()->searchable()->required()
                ->default(fn (): array => Status::query()->where('name', 'ACTIVO')->pluck('id')->all())
                ->helperText('Solo estos estados pueden presentar candidatos, votar y recibir avisos del concurso.'),
            DateTimePicker::make('nomination_starts_at')->label('Inicio de candidaturas')->required()->seconds(false)->default("$year-11-01 00:00:00"),
            DateTimePicker::make('nomination_ends_at')->label('Fin de candidaturas')->required()->seconds(false)->after('nomination_starts_at')->default("$year-11-30 23:59:59"),
            DateTimePicker::make('voting_starts_at')->label('Inicio de votación')->required()->seconds(false)->after('nomination_ends_at')->default("$year-12-01 00:00:00"),
            DateTimePicker::make('voting_ends_at')->label('Fin de votación')->required()->seconds(false)->after('voting_starts_at')->default("$year-12-14 23:59:59"),
        ]);
    }
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('year')->label('Año')->sortable(),
            IconColumn::make('enabled')->label('Activo')->boolean(),
            TextColumn::make('nomination_starts_at')->label('Candidaturas')->date('d/m/Y'),
            TextColumn::make('voting_starts_at')->label('Votación')->date('d/m/Y'),
        ])->recordActions([EditAction::make()]);
    }
    public static function getPages(): array
    {
        return ['index'=>ListClipYearContest::route('/'),'create'=>CreateClipYearContest::route('/create'),'edit'=>EditClipYearContest::route('/{record}/edit')];
    }
}
