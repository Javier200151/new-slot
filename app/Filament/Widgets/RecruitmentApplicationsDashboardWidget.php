<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RecruitmentApplications\RecruitmentApplicationResource;
use App\Models\ContactSubmission;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class RecruitmentApplicationsDashboardWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 0;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('recruitment-applications.manage');
    }

    public static function pendingApprovedQuery(): Builder
    {
        return ContactSubmission::query()
            ->where('is_recruitment', true)
            ->where('recruitment_review_status', ContactSubmission::REVIEW_APPROVED)
            ->whereNotNull('recruitment_matched_user_id')
            ->whereNull('recruited_at');
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Alistados aprobados')
            ->description('Solicitudes aprobadas con usuario asociado que todavía no han entrado en estado RECLUTA.')
            ->query(fn (): Builder => static::pendingApprovedQuery()
                ->with(['recruitmentMatchedUser.status', 'recruitmentInterviewer'])
                ->latest('recruitment_reviewed_at'))
            ->columns([
                TextColumn::make('nickname')
                    ->label('Alistado')
                    ->url(fn (ContactSubmission $record): string => RecruitmentApplicationResource::getUrl('edit', ['record' => $record])),
                TextColumn::make('recruitmentMatchedUser.nick')
                    ->label('Usuario')
                    ->default('—'),
                TextColumn::make('dashboard_status')
                    ->label('Estado')
                    ->state(fn (ContactSubmission $record): string => $record->recruited_at
                        ? 'Reclutado'
                        : 'Pendiente de acceder al reclutamiento')
                    ->badge()
                    ->color(fn (ContactSubmission $record): string => $record->recruited_at ? 'success' : 'warning'),
                TextColumn::make('recruitmentInterviewer.nick')
                    ->label('Entrevistador')
                    ->default('—'),
                TextColumn::make('recruitment_reviewed_at')
                    ->label('Aprobado')
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
