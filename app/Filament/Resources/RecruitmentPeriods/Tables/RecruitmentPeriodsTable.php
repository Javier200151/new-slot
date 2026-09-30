<?php

namespace App\Filament\Resources\RecruitmentPeriods\Tables;

use App\Models\CommunityDiary;
use App\Models\RecruitmentPeriod;
use App\Services\RecruitmentParticipationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecruitmentPeriodsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Recluta')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('tutor.nick')
                    ->label('Tutor')
                    ->default('—')
                    ->searchable(),
                TextColumn::make('started_at')
                    ->label('Fecha de ingreso')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('Pendiente')
                    ->sortable(),
                TextColumn::make('process_status')
                    ->label('Estado del proceso')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        RecruitmentPeriod::PROCESS_PENDING_TUTOR => 'Pendiente de tutor',
                        RecruitmentPeriod::PROCESS_IN_PROGRESS => 'En curso',
                        RecruitmentPeriod::PROCESS_PENDING_PROMOTION => 'Pendiente de promocionar',
                        default => $state,
                    })
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        RecruitmentPeriod::PROCESS_PENDING_PROMOTION => 'success',
                        RecruitmentPeriod::PROCESS_PENDING_TUTOR => 'warning',
                        default => 'info',
                    }),
                TextColumn::make('tutorials_status')
                    ->label('Tutorías')
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        RecruitmentPeriod::TUTORIALS_NO => 'No',
                        RecruitmentPeriod::TUTORIALS_PARTIAL => 'Parcial',
                        RecruitmentPeriod::TUTORIALS_YES => 'Sí',
                        default => $state,
                    })
                    ->badge(),
                TextColumn::make('diary_rating')
                    ->label('Diarios / debriefings')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        RecruitmentPeriod::DIARY_VERY_GOOD => 'Muy bueno',
                        RecruitmentPeriod::DIARY_GOOD => 'Bueno',
                        RecruitmentPeriod::DIARY_IMPROVABLE => 'Mejorable',
                        RecruitmentPeriod::DIARY_DEFICIENT => 'Deficiente',
                        default => '—',
                    })
                    ->badge(),
                TextColumn::make('played_events')
                    ->label('Eventos jugados')
                    ->state(function (RecruitmentPeriod $record): string {
                        if (! $record->started_at) {
                            return 'Pendiente fecha';
                        }

                        return (string) app(RecruitmentParticipationService::class)
                            ->playedEvents($record->user_id, $record->started_at)
                            ->count();
                    })
                    ->badge()
                    ->color(fn (RecruitmentPeriod $record): string => $record->started_at ? 'primary' : 'gray')
                    ->action(
                        Action::make('playedEventsDetails')
                            ->disabled(fn (RecruitmentPeriod $record): bool => ! $record->started_at)
                            ->modalHeading(fn (RecruitmentPeriod $record): string => 'Eventos jugados · ' . ($record->user?->nick ?? 'Recluta'))
                            ->modalWidth('4xl')
                            ->modalSubmitAction(false)
                            ->modalCancelActionLabel('Cerrar')
                            ->modalContent(fn (RecruitmentPeriod $record) => view('filament.recruitment.events-list', [
                                'events' => $record->started_at
                                    ? app(RecruitmentParticipationService::class)->playedEventDetails(
                                        $record->user_id,
                                        $record->started_at,
                                    )
                                    : collect(),
                            ]))
                    ),
                IconColumn::make('official_events_allowed')
                    ->label('Oficiales')
                    ->boolean(),
                TextColumn::make('reinforcementAreas.name')
                    ->label('Refuerzo')
                    ->badge()
                    ->separator(',')
                    ->placeholder('—'),
                TextColumn::make('comments_count')
                    ->label('Comentarios')
                    ->badge(),
            ])
            ->filters([
                SelectFilter::make('process_status')
                    ->label('Estado del proceso')
                    ->options([
                        RecruitmentPeriod::PROCESS_PENDING_TUTOR => 'Pendiente de tutor',
                        RecruitmentPeriod::PROCESS_IN_PROGRESS => 'En curso',
                        RecruitmentPeriod::PROCESS_PENDING_PROMOTION => 'Pendiente de promocionar',
                    ]),
            ])
            ->recordActions([
                Action::make('claim')
                    ->label('Asignarme')
                    ->icon('heroicon-o-hand-raised')
                    ->visible(fn (RecruitmentPeriod $record): bool =>
                        $record->tutor_id === null
                        && (bool) auth()->user()?->getAllPermissions()->contains('name', 'recruitment-area.assign')
                    )
                    ->requiresConfirmation()
                    ->action(function (RecruitmentPeriod $record): void {
                        $record->update([
                            'tutor_id' => auth()->id(),
                            'process_status' => RecruitmentPeriod::PROCESS_IN_PROGRESS,
                        ]);
                        Notification::make()->success()->title('Recluta asignado')->send();
                    }),
                Action::make('diary')
                    ->label(fn (RecruitmentPeriod $record): string => CommunityDiary::query()->where('user_id', $record->user_id)->exists() ? 'Ver diario' : 'Sin diario')
                    ->icon('heroicon-o-book-open')
                    ->url(function (RecruitmentPeriod $record): ?string {
                        $diary = CommunityDiary::query()->where('user_id', $record->user_id)->first();
                        return $diary ? route('community.diary.show', $diary) : null;
                    })
                    ->disabled(fn (RecruitmentPeriod $record): bool => ! CommunityDiary::query()->where('user_id', $record->user_id)->exists())
                    ->openUrlInNewTab(),
                EditAction::make()->label('Gestionar'),
            ]);
    }
}
