<?php

namespace App\Filament\Resources\ClipYearNominations;

use App\Filament\Resources\ClipYearNominations\Pages\ListClipYearNominations;
use App\Models\ClipYearNomination;
use App\Models\User;
use App\Models\Event;
use App\Services\CommunityClipService;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\DB;
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
    public static function canEdit($record): bool { return self::canViewAny(); }
    public static function canDelete($record): bool { return self::canViewAny(); }
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
                Action::make('editCandidate')->label('Editar título y protagonistas')->icon('heroicon-o-pencil-square')
                    ->visible(fn (ClipYearNomination $record): bool => $record->clip()->exists())
                    ->fillForm(function (ClipYearNomination $record): array {
                        $clip = $record->clip;
                        return [
                            'title' => $clip?->title ?? '',
                            'description' => $record->description,
                            'event_id' => $clip?->event_id,
                            'protagonist_user_ids' => $clip?->protagonists()->pluck('users.id')->map(fn ($id) => (int) $id)->all() ?? [],
                        ];
                    })
                    ->form([
                        TextInput::make('title')->label('Título')->required()->maxLength(255),
                        Textarea::make('description')->label('Descripción')->rows(3)->maxLength(900),
                        Select::make('event_id')->label('Evento')->options(fn (): array => Event::query()->orderByDesc('date')->limit(250)->pluck('name', 'id')->all())->searchable(),
                        Select::make('protagonist_user_ids')->label('Protagonistas')->multiple()->searchable()->preload()
                            ->options(fn (): array => User::query()->orderBy('nick')->pluck('nick', 'id')->all())
                            ->maxItems(12),
                    ])
                    ->action(function (ClipYearNomination $record, array $data): void {
                        abort_unless(self::canViewAny(), 403);
                        DB::transaction(function () use ($record, $data): void {
                            $clip = $record->clip;
                            abort_unless($clip, 404);
                            app(CommunityClipService::class)->updatePresentation(
                                $clip, trim((string) $data['title']),
                                filled($data['event_id'] ?? null) ? (int) $data['event_id'] : null,
                                array_map('intval', (array) ($data['protagonist_user_ids'] ?? []))
                            );
                            $record->update(['description' => $data['description'] ?? null]);
                        });
                    }),
                Action::make('toggle')->label(fn (ClipYearNomination $record) => $record->is_approved ? 'Retirar' : 'Restablecer')
                    ->requiresConfirmation()->action(function (ClipYearNomination $record): void {
                        abort_unless(self::canViewAny(), 403);
                        $record->update(['is_approved' => ! $record->is_approved]);
                    }),
                Action::make('deleteCandidate')->label('Eliminar candidatura')->icon('heroicon-o-trash')
                    ->color('danger')->requiresConfirmation()
                    ->modalDescription('El miembro podrá presentar otra candidatura si el plazo sigue abierto. Se eliminarán los votos de esta candidatura.')
                    ->action(function (ClipYearNomination $record): void {
                        abort_unless(self::canViewAny(), 403);
                        $record->delete();
                    }),
                Action::make('deleteClip')->label('Eliminar clip')->icon('heroicon-o-x-circle')
                    ->color('danger')->visible(fn (ClipYearNomination $record): bool => $record->clip()->exists())
                    ->requiresConfirmation()
                    ->modalDescription('Retira el clip de la comunidad y elimina las candidaturas vinculadas. Esta acción no se puede deshacer desde aquí.')
                    ->action(function (ClipYearNomination $record): void {
                        abort_unless(self::canViewAny(), 403);
                        DB::transaction(function () use ($record): void {
                            $record->clip?->delete();
                        });
                    }),
            ])->toolbarActions([BulkActionGroup::make([
                BulkAction::make('withdraw')->label('Retirar seleccionados')->requiresConfirmation()
                    ->action(fn (Collection $records) => $records->each(fn ($record) => $record->update(['is_approved'=>false]))),
            ])]);
    }
    public static function getPages(): array { return ['index'=>ListClipYearNominations::route('/')]; }
}
