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
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\HtmlString;

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
                TextColumn::make('tier_ratings')
                    ->label('TIERs')
                    ->html()
                    ->state(function (ContactSubmission $record): HtmlString {
                        $badges = $record->recruitmentTierRatingsSummary()
                            ->map(function (array $rating): string {
                                $style = match ($rating['color']) {
                                    'success' => 'background:rgba(22,163,74,.16);border:1px solid rgba(22,163,74,.35);color:#4ade80;',
                                    'warning' => 'background:rgba(234,179,8,.18);border:1px solid rgba(234,179,8,.35);color:#facc15;',
                                    'danger' => 'background:rgba(249,115,22,.18);border:1px solid rgba(249,115,22,.35);color:#fb923c;',
                                    default => 'background:rgba(107,114,128,.18);border:1px solid rgba(107,114,128,.35);color:#cbd5e1;',
                                };

                                $title = e($rating['reason'] ?: 'Sin comentario');
                                $label = e($rating['label']);

                                return "<span title=\"{$title}\" style=\"display:inline-flex;align-items:center;border-radius:999px;padding:.2rem .5rem;font-size:.72rem;font-weight:700;{$style}\">{$label}</span>";
                            })
                            ->implode(' ');

                        return new HtmlString($badges !== ''
                            ? '<div style="display:flex;flex-wrap:wrap;gap:.35rem;">' . $badges . '</div>'
                            : '<span style="color:#94a3b8;">Sin valoraciones</span>');
                    }),
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
                    ->label('Tiene algún TIER')
                    ->options(ContactSubmission::recruitmentTierOptions())
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;

                        if (! filled($value)) {
                            return $query;
                        }

                        return $query->whereHas('recruitmentTierRatings', fn (Builder $tierQuery): Builder => $tierQuery->where('tier', (int) $value));
                    }),
            ])
            ->defaultSort('created_at', 'desc')
            ->recordActions([
                EditAction::make()->label('Revisar'),
                Action::make('setTier')
                    ->label('Mi TIER')
                    ->icon('heroicon-o-tag')
                    ->color(function (ContactSubmission $record): string {
                        $rating = $record->currentUserRecruitmentTierRating(auth()->id());

                        return $rating?->tierColor() ?? 'gray';
                    })
                    ->fillForm(function (ContactSubmission $record): array {
                        $rating = $record->currentUserRecruitmentTierRating(auth()->id());

                        return [
                            'tier' => $rating?->tier,
                            'reason' => $rating?->reason,
                        ];
                    })
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
                            ->title('Tu TIER se ha guardado')
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
            ]);
    }
}
