<?php

namespace App\Filament\Resources\RecruitmentReentryReviews\Tables;

use App\Models\RecruitmentReentryReview;
use App\Models\User;
use App\Services\RecruitmentPeriodService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class RecruitmentReentryReviewsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Usuario')
                    ->searchable(),

                TextColumn::make('review_type')
                    ->label('Motivo')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        RecruitmentReentryReview::TYPE_RESERVE_TO_ACTIVE => 'Retorno desde reserva',
                        default => 'Repetición de reclutamiento',
                    })
                    ->badge()
                    ->color(fn (?string $state): string => $state === RecruitmentReentryReview::TYPE_RESERVE_TO_ACTIVE
                        ? 'warning'
                        : 'info'),

                TextColumn::make('previousPeriod.period_number')
                    ->label('Último periodo')
                    ->placeholder('—'),

                TextColumn::make('previousPeriod.ended_at')
                    ->label('Fin anterior')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),

                TextColumn::make('detected_at')
                    ->label('Detectado')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),

                TextColumn::make('warning')
                    ->label('Acción necesaria')
                    ->state(fn (RecruitmentReentryReview $record): string => $record->isReserveReturn()
                        ? 'Debe realizar una tutoría de reincorporación y registrar qué tutor la realizó.'
                        : 'Ya completó anteriormente un periodo PROMOCIONADO. Confirmar si debe repetir reclutamiento.')
                    ->wrap(),
            ])
            ->recordActions([
                Action::make('approveReserveTutoring')
                    ->label('Tutoría aprobada')
                    ->color('success')
                    ->icon('heroicon-o-check-badge')
                    ->visible(fn (RecruitmentReentryReview $record): bool => $record->isReserveReturn())
                    ->modalHeading('Registrar tutoría aprobada')
                    ->modalDescription('Selecciona quién realizó la tutoría de reincorporación. Al confirmar, la revisión quedará resuelta.')
                    ->form([
                        Select::make('tutorial_tutor_user_id')
                            ->label('Tutor que realizó la tutoría')
                            ->options(fn (): array => User::query()
                                ->permission('recruitment-area.access')
                                ->orderBy('nick')
                                ->pluck('nick', 'id')
                                ->all())
                            ->searchable()
                            ->preload()
                            ->required(),
                    ])
                    ->action(function (RecruitmentReentryReview $record, array $data): void {
                        app(RecruitmentPeriodService::class)->approveReserveReturnTutoring(
                            $record,
                            (int) $data['tutorial_tutor_user_id'],
                            auth()->id(),
                        );

                        Notification::make()
                            ->success()
                            ->title('Tutoría de reincorporación aprobada')
                            ->send();
                    }),

                Action::make('approveReserveWithoutTutoring')
                    ->label('Aprobar sin retutoría')
                    ->color('warning')
                    ->icon('heroicon-o-forward')
                    ->visible(fn (RecruitmentReentryReview $record): bool => $record->isReserveReturn())
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar reincorporación sin retutoría')
                    ->modalDescription('Confirma que este miembro puede reincorporarse sin realizar una retutoría. Se guardará esta decisión en el histórico de la revisión y se levantará el bloqueo para apuntarse a eventos.')
                    ->action(function (RecruitmentReentryReview $record): void {
                        app(RecruitmentPeriodService::class)->approveReserveReturnWithoutTutoring(
                            $record,
                            auth()->id(),
                        );

                        Notification::make()
                            ->success()
                            ->title('Reincorporación aprobada sin retutoría')
                            ->send();
                    }),

                Action::make('start')
                    ->label('Iniciar nuevo periodo')
                    ->color('success')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (RecruitmentReentryReview $record): bool => ! $record->isReserveReturn())
                    ->requiresConfirmation()
                    ->action(function (RecruitmentReentryReview $record): void {
                        app(RecruitmentPeriodService::class)->confirmPromotedReentry($record, auth()->id());

                        Notification::make()
                            ->success()
                            ->title('Nuevo periodo iniciado')
                            ->send();
                    }),

                Action::make('error')
                    ->label('Cambio de status por error')
                    ->color('gray')
                    ->icon('heroicon-o-x-circle')
                    ->requiresConfirmation()
                    ->action(function (RecruitmentReentryReview $record): void {
                        app(RecruitmentPeriodService::class)->resolvePromotedReentryAsStatusError(
                            $record,
                            auth()->id(),
                        );

                        Notification::make()
                            ->success()
                            ->title('Reincorporación descartada')
                            ->send();
                    }),
            ]);
    }
}
