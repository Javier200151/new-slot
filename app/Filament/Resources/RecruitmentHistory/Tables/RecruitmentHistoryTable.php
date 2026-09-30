<?php
namespace App\Filament\Resources\RecruitmentHistory\Tables;
use App\Models\RecruitmentPeriod;
use App\Services\RecruitmentParticipationService;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;
use Filament\Tables\Table;
class RecruitmentHistoryTable { public static function configure(Table $table): Table { return $table
->columns([
 TextColumn::make('user_nick_snapshot')->label('Recluta')->default(fn($record)=>$record->user?->nick)->searchable(),
 TextColumn::make('period_number')->label('Periodo')->sortable(),
 TextColumn::make('started_at')->label('Inicio')->dateTime('d/m/Y H:i')->placeholder('Sin fecha'),
 TextColumn::make('ended_at')->label('Fin')->dateTime('d/m/Y H:i')->sortable(),
 TextColumn::make('tutor_nick_snapshot')->label('Tutor')->default('—')->searchable(),
 TextColumn::make('result')->label('Resultado')->formatStateUsing(fn(?string $state)=>match($state){RecruitmentPeriod::RESULT_PROMOTED=>'PROMOCIONADO',RecruitmentPeriod::RESULT_NOT_PROMOTED=>'NO PROMOCIONADO',default=>'—'})->badge()->color(fn(?string $state)=>$state===RecruitmentPeriod::RESULT_PROMOTED?'success':'danger'),
 TextColumn::make('tutorials_status')->label('Tutorías')->badge(),
 TextColumn::make('diary_rating')->label('Valoración')->badge()->placeholder('—'),
 TextColumn::make('events_played_final')->label('Eventos')->numeric()->placeholder('—')->badge()->color('primary')
     ->action(Action::make('playedEventsDetails')
         ->disabled(fn (RecruitmentPeriod $record): bool => ! $record->started_at || ! $record->ended_at)
         ->modalHeading(fn (RecruitmentPeriod $record): string => 'Eventos jugados · ' . ($record->user_nick_snapshot ?: $record->user?->nick ?: 'Recluta'))
         ->modalWidth('4xl')
         ->modalSubmitAction(false)
         ->modalCancelActionLabel('Cerrar')
         ->modalContent(fn (RecruitmentPeriod $record) => view('filament.recruitment.events-list', [
             'events' => ($record->started_at && $record->ended_at)
                 ? app(RecruitmentParticipationService::class)->playedEventDetails($record->user_id, $record->started_at, $record->ended_at)
                 : collect(),
         ]))),
 TextColumn::make('reinforcementAreas.name')->label('Refuerzos')->badge()->separator(',')->placeholder('—'),
 TextColumn::make('final_status_name')->label('Estado final')->badge(),
 TextColumn::make('comments_count')->label('Comentarios')->badge(),
])->filters([
 SelectFilter::make('result')->label('Resultado')->options([RecruitmentPeriod::RESULT_PROMOTED=>'PROMOCIONADO',RecruitmentPeriod::RESULT_NOT_PROMOTED=>'NO PROMOCIONADO']),
 SelectFilter::make('final_status_name')->label('Estado final')->options(fn()=>RecruitmentPeriod::query()->whereNotNull('final_status_name')->distinct()->orderBy('final_status_name')->pluck('final_status_name','final_status_name')->all()),
 Filter::make('ended_at_range')->label('Fecha de cierre')->form([
     DatePicker::make('from')->label('Desde'),
     DatePicker::make('until')->label('Hasta'),
 ])->query(fn (Builder $query, array $data): Builder => $query
     ->when($data['from'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('ended_at', '>=', $date))
     ->when($data['until'] ?? null, fn (Builder $query, $date): Builder => $query->whereDate('ended_at', '<=', $date))),
])->recordActions([ViewAction::make()]); } }
