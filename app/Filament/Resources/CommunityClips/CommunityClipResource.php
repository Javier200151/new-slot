<?php

namespace App\Filament\Resources\CommunityClips;

use App\Filament\Resources\CommunityClips\Pages\CreateCommunityClip;
use App\Filament\Resources\CommunityClips\Pages\EditCommunityClip;
use App\Filament\Resources\CommunityClips\Pages\ListCommunityClips;
use App\Models\CommunityClip;
use App\Services\CommunityClipService;
use BackedEnum;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\DeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Actions\EditAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Tables\Filters\TrashedFilter;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

class CommunityClipResource extends Resource
{
    protected static string | UnitEnum | null $navigationGroup = 'Streams';
    protected static ?int $navigationSort = 2;
    protected static ?string $model = CommunityClip::class;
    protected static string | BackedEnum | null $navigationIcon = Heroicon::OutlinedFilm;
    protected static ?string $recordTitleAttribute = 'title';
    protected static ?string $modelLabel = 'Clip';
    protected static ?string $pluralModelLabel = 'Clips de la comunidad';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('user_id')->label('Miembro')->relationship('user', 'nick')->searchable()->preload()->nullable(),
            TextInput::make('url')->label('Enlace del clip')->url()->required()->maxLength(600)->columnSpanFull(),
            TextInput::make('title')->label('Título')->maxLength(255),
            Select::make('event_id')->label('Evento')->relationship('event', 'name')->searchable()->nullable(),
            Select::make('protagonist_user_id')->label('Protagonista')->relationship('protagonist', 'nick')->searchable()->nullable(),
            Toggle::make('is_visible')->label('Visible')->default(true),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('title')->label('Título')->limit(55)->searchable(),
            TextColumn::make('user.nick')->label('Miembro')->searchable(),
            TextColumn::make('event.name')->label('Evento')->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('protagonist.nick')->label('Protagonista')->toggleable(),
            TextColumn::make('platform')->label('Plataforma')->badge(),
            TextColumn::make('source')->label('Origen')->formatStateUsing(fn ($state) => $state === 'automatic' ? 'Automático' : 'Manual')->badge(),
            TextColumn::make('is_visible')->label('Publicado')->formatStateUsing(fn ($state) => $state ? 'Sí' : 'No')->badge(),
            TextColumn::make('clip_created_at')->label('Fecha')->dateTime('d/m/Y H:i')->sortable(),
        ])->filters([
            TernaryFilter::make('is_visible')->label('Publicado'),
            SelectFilter::make('platform')->options(['twitch' => 'Twitch', 'youtube' => 'YouTube']),
            TrashedFilter::make(),
        ])->defaultSort('clip_created_at', 'desc')->recordActions([
            Action::make('toggle')->label(fn (CommunityClip $record) => $record->is_visible ? 'Ocultar' : 'Mostrar')
                ->icon('heroicon-o-eye')->authorize('update')->action(fn (CommunityClip $record) => $record->update(['is_visible' => ! $record->is_visible])),
            EditAction::make()->visible(fn (CommunityClip $record) => ! $record->trashed()),
            DeleteAction::make()->visible(fn (CommunityClip $record) => ! $record->trashed()),
            RestoreAction::make()->visible(fn (CommunityClip $record) => $record->trashed()),
        ])->toolbarActions([
            BulkActionGroup::make([
                BulkAction::make('hide')->label('Ocultar seleccionados')->icon('heroicon-o-eye-slash')->requiresConfirmation()
                    ->authorize(fn (): bool => (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('community-clips.update')))->action(function (Collection $records): void {
                        abort_unless(auth()->user()?->hasRole('admin') || auth()->user()?->can('community-clips.update'), 403);
                        $records->each(fn ($record) => ! $record->trashed() ? $record->update(['is_visible' => false]) : null);
                    }),
                BulkAction::make('show')->label('Mostrar seleccionados')->icon('heroicon-o-eye')
                    ->authorize(fn (): bool => (bool) (auth()->user()?->hasRole('admin') || auth()->user()?->can('community-clips.update')))->action(function (Collection $records): void {
                        abort_unless(auth()->user()?->hasRole('admin') || auth()->user()?->can('community-clips.update'), 403);
                        $records->each(fn ($record) => ! $record->trashed() ? $record->update(['is_visible' => true]) : null);
                    }),
                DeleteBulkAction::make(),
                RestoreBulkAction::make(),
            ]),
        ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListCommunityClips::route('/'),
            'create' => CreateCommunityClip::route('/create'),
            'edit' => EditCommunityClip::route('/{record}/edit'),
        ];
    }
}
