<?php

namespace App\Filament\Resources\RecruitmentPeriods\Pages;

use App\Filament\Resources\RecruitmentPeriods\RecruitmentPeriodResource;
use App\Models\CommunityDiary;
use App\Models\RecruitmentPeriod;
use App\Services\RecruitmentPeriodService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;

class EditRecruitmentPeriod extends EditRecord
{
    protected static string $resource = RecruitmentPeriodResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        if (! auth()->user()?->getAllPermissions()->contains('name', 'recruitment-area.assign')) {
            unset($data['tutor_id']);
        }

        if (! in_array($this->record->process_status, [
            RecruitmentPeriod::PROCESS_PENDING_PROMOTION,
            RecruitmentPeriod::PROCESS_PENDING_DISMISSAL,
        ], true)) {
            $data['process_status'] = filled($data['tutor_id'] ?? $this->record->tutor_id)
                ? RecruitmentPeriod::PROCESS_IN_PROGRESS
                : RecruitmentPeriod::PROCESS_PENDING_TUTOR;
        }

        return $data;
    }

    protected function getHeaderActions(): array
    {
        $diary = CommunityDiary::query()->where('user_id', $this->record->user_id)->first();

        return [
            Action::make('diary')
                ->label($diary ? 'Ver diario' : 'Sin diario')
                ->icon('heroicon-o-book-open')
                ->disabled(! $diary)
                ->url($diary ? route('community.diary.show', $diary) : null)
                ->openUrlInNewTab(),

            Action::make('markPromotion')
                ->label('Marcar pendiente de promocionar')
                ->icon('heroicon-o-check-badge')
                ->color('success')
                ->visible(fn (): bool => $this->record->process_status !== RecruitmentPeriod::PROCESS_PENDING_PROMOTION)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(RecruitmentPeriodService::class)->markPromotionPending($this->record, auth()->id());
                    Notification::make()->success()->title('Recluta marcado como pendiente de promocionar')->send();
                    $this->refreshFormData([
                        'process_status',
                        'promotion_pending_at',
                        'promotion_pending_by',
                        'dismissal_pending_at',
                        'dismissal_pending_by',
                    ]);
                }),

            Action::make('clearPromotion')
                ->label('Quitar pendiente de promoción')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => $this->record->process_status === RecruitmentPeriod::PROCESS_PENDING_PROMOTION)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(RecruitmentPeriodService::class)->clearPromotionPending($this->record);
                    Notification::make()->success()->title('Pendiente de promoción retirado')->send();
                    $this->refreshFormData(['process_status', 'promotion_pending_at', 'promotion_pending_by']);
                }),

            Action::make('markDismissal')
                ->label('Marcar pendiente de baja')
                ->icon('heroicon-o-user-minus')
                ->color('danger')
                ->visible(fn (): bool => $this->record->process_status !== RecruitmentPeriod::PROCESS_PENDING_DISMISSAL)
                ->requiresConfirmation()
                ->modalDescription('Esto no cambia el estado del usuario. Solo deja marcada la propuesta de baja para revisión.')
                ->action(function (): void {
                    app(RecruitmentPeriodService::class)->markDismissalPending($this->record, auth()->id());
                    Notification::make()->success()->title('Recluta marcado como pendiente de baja')->send();
                    $this->refreshFormData([
                        'process_status',
                        'promotion_pending_at',
                        'promotion_pending_by',
                        'dismissal_pending_at',
                        'dismissal_pending_by',
                    ]);
                }),

            Action::make('clearDismissal')
                ->label('Quitar pendiente de baja')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (): bool => $this->record->process_status === RecruitmentPeriod::PROCESS_PENDING_DISMISSAL)
                ->requiresConfirmation()
                ->action(function (): void {
                    app(RecruitmentPeriodService::class)->clearDismissalPending($this->record);
                    Notification::make()->success()->title('Pendiente de baja retirado')->send();
                    $this->refreshFormData(['process_status', 'dismissal_pending_at', 'dismissal_pending_by']);
                }),
        ];
    }
}
