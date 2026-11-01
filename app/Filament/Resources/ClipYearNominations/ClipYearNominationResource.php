<?php

namespace App\Filament\Resources\ClipYearNominations;

use App\Filament\Resources\ClipYearNominations\Pages\ListClipYearNominations;
use App\Models\ClipYearNomination;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Table;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Illuminate\Database\Eloquent\Collection;
use BackedEnum;
use UnitEnum;

class ClipYearNominationResource extends Resource
{
    protected static ?string $model = ClipYearNomination::class;
    protected static string | UnitEnum | null $navigationGroup = 'Streams';
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-hand-raised';
    protected static ?string $navigationLabel = 'Clip del año · Candidatos';
    protected static ?string $modelLabel = 'Candidato';
    protected static ?string $pluralModelLabel = 'Candidatos';
    public static function canViewAny(): bool { return (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('clip-contest.manage')); }
    public static function canCreate(): bool { return false; }
    public static function canEdit($record): bool { return false; }
    public static function canDelete($record): bool { return false; }
    public static function form(Schema $schema): Schema { return $schema->components([]); }
    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('contest.year')->label('Edición')->sortable(),
            TextColumn::make('user.nick')->label('Presentado por')->searchable(),
            TextColumn::make('clip.title')->label('Clip')->limit(60),
            IconColumn::make('is_approved')->label('Participa')->boolean(),
            TextColumn::make('created_at')->label('Fecha')->dateTime('d/m/Y H:i'),
        ])->filters([SelectFilter::make('contest_id')->label('Año')->relationship('contest','year')])
            ->recordActions([
                Action::make('toggle')->label(fn (ClipYearNomination $record) => $record->is_approved ? 'Retirar' : 'Restablecer')
                ->requiresConfirmation()->action(fn (ClipYearNomination $record) => $record->update(['is_approved'=>!$record->is_approved])),
            ])->toolbarActions([BulkActionGroup::make([
                BulkAction::make('withdraw')->label('Retirar seleccionados')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn ($record) => $record->update(['is_approved'=>false]))),
            ])]);
    }
    public static function getPages(): array { return ['index'=>ListClipYearNominations::route('/')]; }
}
