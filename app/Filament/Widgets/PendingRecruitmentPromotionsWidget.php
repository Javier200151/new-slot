<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\RecruitmentPeriods\RecruitmentPeriodResource;
use App\Models\RecruitmentPeriod;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class PendingRecruitmentPromotionsWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    public static function canView(): bool
    {
        return auth()->check();
    }

    public function table(Table $table): Table
    {
        return $table
            ->heading('Reclutas pendientes de promocionar')
            ->description('Aviso visible para todos los usuarios con acceso a Filament.')
            ->query(fn (): Builder => RecruitmentPeriod::query()
                ->whereNull('ended_at')
                ->whereNotNull('open_user_id')
                ->where('process_status', RecruitmentPeriod::PROCESS_PENDING_PROMOTION)
                ->with(['user', 'tutor']))
            ->columns([
                TextColumn::make('user.nick')
                    ->label('Recluta')
                    ->url(fn (RecruitmentPeriod $record): ?string =>
                        auth()->user()?->getAllPermissions()->contains('name', 'recruitment-area.access')
                            ? RecruitmentPeriodResource::getUrl('edit', ['record' => $record])
                            : null
                    ),
                TextColumn::make('tutor.nick')->label('Tutor')->default('—'),
                TextColumn::make('promotion_pending_at')
                    ->label('Pendiente desde')
                    ->since()
                    ->placeholder('—'),
            ])
            ->paginated(false);
    }
}
