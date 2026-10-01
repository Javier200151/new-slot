<?php

namespace App\Filament\Resources\RecruitmentApplications\Pages;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use App\Models\ContactSubmission;
use App\Models\User;
use App\Services\RecruitmentApplicationService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentApplication extends EditRecord
{
    protected static string $resource = RecruitmentApplicationResource::class;

    public function getTitle(): string
    {
        return $this->record->nickname
            ? 'Solicitud de ' . $this->record->nickname
            : 'Solicitud de alistamiento';
    }

    protected function mutateFormDataBeforeSave(array $data): array
    {
        $data['read_at'] ??= now();

        return $data;
    }

    protected function getFormActions(): array
    {
        return [];
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('setTier')
                ->label(function (): string {
                    $rating = $this->record->currentUserRecruitmentTierRating(auth()->id());

                    return $rating ? 'Cambiar mi ' . $rating->tierLabel() : 'Marcar mi TIER';
                })
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->fillForm(function (): array {
                    $rating = $this->record->currentUserRecruitmentTierRating(auth()->id());

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
                        ->helperText('Opcional.')
                        ->rows(5)
                        ->maxLength(2000),
                ])
                ->modalHeading('Valorar solicitud')
                ->modalSubmitActionLabel('Guardar valoración')
                ->action(function (array $data): void {
                    $this->record = app(RecruitmentApplicationService::class)
                        ->setTier(
                            $this->record,
                            (int) $data['tier'],
                            $data['reason'] ?? null,
                            auth()->id(),
                        );
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Tu valoración TIER se ha guardado')
                        ->send();
                }),

            Action::make('assignInterviewer')
                ->label(fn (): string => $this->record->recruitment_interviewer_user_id
                    ? 'Cambiar entrevistador'
                    : 'Asignar entrevistador')
                ->icon('heroicon-o-user-plus')
                ->color('info')
                ->fillForm(fn (): array => [
                    'interviewer_user_id' => $this->record->recruitment_interviewer_user_id,
                ])
                ->form([
                    Select::make('interviewer_user_id')
                        ->label('Entrevistador')
                        ->options(fn (): array => RecruitmentApplicationResource::interviewerOptions())
                        ->searchable()
                        ->preload()
                        ->required()
                        ->helperText('Puedes seleccionarte a ti mismo o elegir a otro gestor de alistados.'),
                ])
                ->modalHeading('Asignar entrevistador')
                ->modalSubmitActionLabel('Guardar')
                ->action(function (array $data): void {
                    $interviewer = User::query()->findOrFail((int) $data['interviewer_user_id']);

                    $this->record = app(RecruitmentApplicationService::class)
                        ->assignInterviewer($this->record, $interviewer);
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Entrevistador actualizado')
                        ->body($interviewer->nick)
                        ->send();
                }),

            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->visible(fn (): bool => $this->record->recruitment_review_status !== ContactSubmission::REVIEW_APPROVED)
                ->requiresConfirmation()
                ->modalDescription('Si existe un usuario con el mismo email, se enlazará automáticamente.')
                ->action(function (): void {
                    $this->record = app(RecruitmentApplicationService::class)
                        ->approve($this->record, auth()->id());
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Solicitud aprobada')
                        ->body($this->record->recruitmentWorkflowLabel())
                        ->send();
                }),

            Action::make('discard')
                ->label('Descartar')
                ->icon('heroicon-o-x-circle')
                ->color('danger')
                ->visible(fn (): bool => $this->record->recruitment_review_status !== ContactSubmission::REVIEW_DISCARDED)
                ->requiresConfirmation()
                ->modalDescription('Podrás cambiar esta decisión más adelante.')
                ->action(function (): void {
                    $this->record = app(RecruitmentApplicationService::class)
                        ->discard($this->record, auth()->id());
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('Solicitud descartada')
                        ->send();
                }),

            Action::make('resetDecision')
                ->label('Volver a no valorada')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->visible(fn (): bool => $this->record->recruitment_review_status !== ContactSubmission::REVIEW_UNREVIEWED)
                ->requiresConfirmation()
                ->action(function (): void {
                    $this->record = app(RecruitmentApplicationService::class)
                        ->resetDecision($this->record);
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title('La solicitud vuelve a estar sin valorar')
                        ->send();
                }),
        ];
    }
}
