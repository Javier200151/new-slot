<?php

namespace App\Filament\Resources\RecruitmentApplications\Pages;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use App\Models\ContactSubmission;
use App\Services\RecruitmentApplicationService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Illuminate\Database\Eloquent\Builder;

class BoardRecruitmentApplications extends Page
{
    protected static string $resource = RecruitmentApplicationResource::class;

    protected string $view = 'filament.resources.recruitment-applications.pages.board-recruitment-applications';

    public string $search = '';

    public function mount(): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);
    }

    public function getTitle(): string
    {
        return 'Gestión de alistados';
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('listView')
                ->label('Ver lista')
                ->icon('heroicon-o-list-bullet')
                ->url(RecruitmentApplicationResource::getUrl('list')),
        ];
    }

    public function getViewData(): array
    {
        return [
            'columns' => $this->getBoardColumns(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    protected function getBoardColumns(): array
    {
        return [
            [
                'status' => ContactSubmission::REVIEW_UNREVIEWED,
                'label' => 'No valoradas',
                'items' => $this->getRecordsForStatus(ContactSubmission::REVIEW_UNREVIEWED),
            ],
            [
                'status' => ContactSubmission::REVIEW_APPROVED,
                'label' => 'Aprobadas / reclutados',
                'items' => $this->getRecordsForStatus(ContactSubmission::REVIEW_APPROVED),
            ],
            [
                'status' => ContactSubmission::REVIEW_DISCARDED,
                'label' => 'Descartadas',
                'items' => $this->getRecordsForStatus(ContactSubmission::REVIEW_DISCARDED),
            ],
        ];
    }

    public function moveToStatus(int $recordId, string $status): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);

        $record = RecruitmentApplicationResource::getEloquentQuery()->findOrFail($recordId);
        $service = app(RecruitmentApplicationService::class);

        match ($status) {
            ContactSubmission::REVIEW_APPROVED => $service->approve($record, auth()->id()),
            ContactSubmission::REVIEW_DISCARDED => $service->discard($record, auth()->id()),
            default => $service->resetDecision($record),
        };

        Notification::make()
            ->success()
            ->title('Solicitud movida')
            ->body(match ($status) {
                ContactSubmission::REVIEW_APPROVED => 'La solicitud se ha movido a Aprobadas / reclutados.',
                ContactSubmission::REVIEW_DISCARDED => 'La solicitud se ha movido a Descartadas.',
                default => 'La solicitud vuelve a No valoradas.',
            })
            ->send();
    }

    public function deleteApplication(int $recordId): void
    {
        abort_unless(static::getResource()::canViewAny(), 403);

        $record = RecruitmentApplicationResource::getEloquentQuery()->findOrFail($recordId);
        $label = $record->nickname ?: $record->email;
        $record->delete();

        Notification::make()
            ->success()
            ->title('Solicitud eliminada')
            ->body($label)
            ->send();
    }

    /**
     * @return \Illuminate\Support\Collection<int, ContactSubmission>
     */
    protected function getRecordsForStatus(string $status)
    {
        return $this->getBaseQuery()
            ->where('recruitment_review_status', $status)
            ->get();
    }

    protected function getBaseQuery(): Builder
    {
        return RecruitmentApplicationResource::getEloquentQuery()
            ->when(
                filled($this->search),
                function (Builder $query): void {
                    $term = trim($this->search);

                    $query->where(function (Builder $query) use ($term): void {
                        $query->where('nickname', 'like', "%{$term}%")
                            ->orWhere('email', 'like', "%{$term}%")
                            ->orWhere('message', 'like', "%{$term}%")
                            ->orWhere('full_name', 'like', "%{$term}%");
                    });
                },
            )
            ->orderByDesc('created_at');
    }
}
