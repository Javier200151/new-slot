<?php

namespace App\Filament\Resources\RecruitmentApplications\Tables;

use App\Models\ContactSubmission;
use App\Services\RecruitmentApplicationService;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class RecruitmentApplicationsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('workflow_status')
                    ->label('Estado')
                    ->state(fn (ContactSubmission $record): string => $record->recruitmentWorkflowLabel())
                    ->badge()
                    ->color(fn (ContactSubmission $record): string => $record->recruitmentWorkflowColor())
                    ->sortable(query: fn ($query, string $direction) => $query->orderBy('recruitment_review_status', $direction)),
                TextColumn::make('recruitment_tier')
                    ->label('TIER')
                    ->state(fn (ContactSubmission $record): string => $record->recruitmentTierLabel())
                    ->badge()
                    ->color(fn (ContactSubmission $record): string => $record->recruitmentTierColor())
                    ->description(fn (ContactSubmission $record): ?string => filled($record->recruitment_tier_reason)
                        ? (string) str($record->recruitment_tier_reason)->limit(55)
                        : null)
                    ->sortable(),
                TextColumn::make('nickname')
                    ->label('Nick')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('email')
                    ->label('Email')
                    ->searchable(),
                TextColumn::make('recruitmentMatchedUser.nick')
                    ->label('Usuario')
                    ->default('—')
                    ->description(fn (ContactSubmission $record): ?string => $record->recruitmentMatchedUser?->status?->name),
                TextColumn::make('recruitmentInterviewer.nick')
                    ->label('Entrevistador')
                    ->default('—'),
                TextColumn::make('recruitment_comments_count')
                    ->label('Comentarios')
                    ->badge(),
                TextColumn::make('created_at')
                    ->label('Recibida')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('recruitment_review_status')
                    ->label('Valoración')
                    ->options([
                        ContactSubmission::REVIEW_UNREVIEWED => 'No valoradas',
                        ContactSubmission::REVIEW_DISCARDED => 'Descartadas',
                        ContactSubmission::REVIEW_APPROVED => 'Aprobadas / reclutados',
                    ]),
                SelectFilter::make('recruitment_tier')
                    ->label('TIER')
                    ->options(ContactSubmission::recruitmentTierOptions()),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->label('Revisar'),
                Action::make('setTier')
                    ->label('TIER')
                    ->icon('heroicon-o-tag')
                    ->color(fn (ContactSubmission $record): string => $record->recruitmentTierColor())
                    ->fillForm(fn (ContactSubmission $record): array => [
                        'tier' => $record->recruitment_tier,
                        'reason' => $record->recruitment_tier_reason,
                    ])
                    ->form([
                        Select::make('tier')
                            ->label('Clasificación')
                            ->options(ContactSubmission::recruitmentTierOptions())
                            ->required()
                            ->native(false),
                        Textarea::make('reason')
                            ->label('Motivo de la clasificación')
                            ->helperText('Opcional. Puedes dejarlo vacío.')
                            ->rows(4)
                            ->maxLength(2000),
                    ])
                    ->modalHeading('Clasificar solicitud')
                    ->modalSubmitActionLabel('Guardar TIER')
                    ->action(function (array $data, ContactSubmission $record): void {
                        $record = app(RecruitmentApplicationService::class)
                            ->setTier(
                                $record,
                                (int) $data['tier'],
                                $data['reason'] ?? null,
                                auth()->id(),
                            );

                        Notification::make()
                            ->success()
                            ->title($record->recruitmentTierLabel() . ' guardado')
                            ->send();
                    }),
                Action::make('approve')
                    ->label('Aprobar')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactSubmission $record): bool => $record->recruitment_review_status !== ContactSubmission::REVIEW_APPROVED)
                    ->requiresConfirmation()
                    ->action(function (ContactSubmission $record): void {
                        $record = app(RecruitmentApplicationService::class)
                            ->approve($record, auth()->id());

                        Notification::make()
                            ->success()
                            ->title('Solicitud aprobada')
                            ->body($record->recruitmentWorkflowLabel())
                            ->send();
                    }),
                Action::make('discard')
                    ->label('Descartar')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ContactSubmission $record): bool => $record->recruitment_review_status !== ContactSubmission::REVIEW_DISCARDED)
                    ->requiresConfirmation()
                    ->action(function (ContactSubmission $record): void {
                        app(RecruitmentApplicationService::class)
                            ->discard($record, auth()->id());

                        Notification::make()
                            ->success()
                            ->title('Solicitud descartada')
                            ->send();
                    }),
                Action::make('resetDecision')
                    ->label('No valorada')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->color('gray')
                    ->visible(fn (ContactSubmission $record): bool => $record->recruitment_review_status !== ContactSubmission::REVIEW_UNREVIEWED)
                    ->requiresConfirmation()
                    ->action(function (ContactSubmission $record): void {
                        app(RecruitmentApplicationService::class)
                            ->resetDecision($record);
                    }),
                Action::make('deleteApplication')
                    ->label('Eliminar')
                    ->icon('heroicon-o-trash')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalHeading('Eliminar solicitud de alistamiento')
                    ->modalDescription('La solicitud se eliminará permanentemente junto con sus comentarios y valoraciones TIER.')
                    ->modalSubmitActionLabel('Eliminar definitivamente')
                    ->action(function (ContactSubmission $record): void {
                        $label = $record->nickname ?: $record->email;
                        $record->delete();

                        Notification::make()
                            ->success()
                            ->title('Solicitud eliminada')
                            ->body($label)
                            ->send();
                    }),
            ]);
    }
}
