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
use Filament\Support\Enums\Size;
use Illuminate\Contracts\View\View;

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

    public function getHeader(): ?View
    {
        return view('filament.resources.recruitment-applications.partials.edit-header');
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('approve')
                ->label('Aprobar')
                ->icon('heroicon-o-check-circle')
                ->color('success')
                ->size(Size::Medium)
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
                ->color('warning')
                ->size(Size::Medium)
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

            Action::make('deleteApplication')
                ->label('Eliminar solicitud')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->size(Size::Medium)
                ->requiresConfirmation()
                ->modalHeading('Eliminar solicitud de alistamiento')
                ->modalDescription('La solicitud, sus comentarios y sus valoraciones TIER se eliminarán permanentemente. Úsalo para duplicados o pruebas.')
                ->modalSubmitActionLabel('Eliminar definitivamente')
                ->action(function (): void {
                    $label = $this->record->nickname ?: $this->record->email;
                    $this->record->delete();

                    Notification::make()
                        ->success()
                        ->title('Solicitud eliminada')
                        ->body($label)
                        ->send();

                    $this->redirect(RecruitmentApplicationResource::getUrl('index'));
                }),

            Action::make('setTier')
                ->label(function (): string {
                    $rating = $this->record->currentUserRecruitmentTierRating(auth()->id());

                    return $rating ? 'Cambiar mi ' . $rating->tierLabel() : 'Marcar mi TIER';
                })
                ->icon('heroicon-o-tag')
                ->color('gray')
                ->size(Size::Small)
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
                ->size(Size::Small)
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

            Action::make('linkMatchedUser')
                ->label(fn (): string => $this->record->recruitment_matched_user_id
                    ? 'Cambiar usuario asociado'
                    : 'Asociar usuario')
                ->icon('heroicon-o-link')
                ->color('info')
                ->size(Size::Small)
                ->fillForm(fn (): array => [
                    'matched_user_id' => $this->record->recruitment_matched_user_id,
                ])
                ->form([
                    Select::make('matched_user_id')
                        ->label('Usuario asociado')
                        ->options(fn (): array => User::query()
                            ->with('status')
                            ->orderBy('nick')
                            ->get()
                            ->mapWithKeys(fn (User $user): array => [
                                $user->id => trim($user->nick . ' · ' . $user->email . ($user->status?->name ? ' · ' . $user->status->name : '')),
                            ])
                            ->all())
                        ->searchable()
                        ->preload()
                        ->placeholder('Sin usuario asociado')
                        ->helperText('Permite enlazar manualmente la ficha aunque el usuario haya usado otro email. Déjalo vacío para quitar la asociación.'),
                ])
                ->modalHeading('Asociar usuario a la solicitud')
                ->modalSubmitActionLabel('Guardar asociación')
                ->action(function (array $data): void {
                    $userId = filled($data['matched_user_id'] ?? null)
                        ? (int) $data['matched_user_id']
                        : null;

                    $user = $userId ? User::query()->findOrFail($userId) : null;

                    $this->record = app(RecruitmentApplicationService::class)
                        ->assignMatchedUser($this->record, $user);
                    $this->fillForm();

                    Notification::make()
                        ->success()
                        ->title($user ? 'Usuario asociado' : 'Asociación eliminada')
                        ->body($user?->nick)
                        ->send();
                }),

            Action::make('resetDecision')
                ->label('Volver a no valorada')
                ->icon('heroicon-o-arrow-uturn-left')
                ->color('gray')
                ->size(Size::Small)
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
