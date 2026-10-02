<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\VeterancyService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

class UpcomingVeteranciesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 3;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('veterancy-settings.view');
    }

    public function table(Table $table): Table
    {
        $service = app(VeterancyService::class);
        $ids = $service->upcomingUsers(60)->pluck('id')->all();

        return $table
            ->heading('Próximas veteranías')
            ->description('Miembros ACTIVOS que alcanzarán su siguiente veteranía dentro de los próximos 60 días de servicio efectivo.')
            ->query(fn (): Builder => User::query()
                ->whereKey($ids)
                ->with(['status', 'metopas']))
            ->columns([
                TextColumn::make('nick')->label('Miembro')->sortable(),
                TextColumn::make('veterancy_level')
                    ->label('Próxima veteranía')
                    ->state(fn (User $record): string => $service->levelLabel($service->summary($record)['next_level'])),
                TextColumn::make('days_remaining')
                    ->label('Faltan')
                    ->state(fn (User $record): string => $service->summary($record)['days_remaining'] . ' días'),
                TextColumn::make('target_date')
                    ->label('Fecha estimada')
                    ->state(fn (User $record): string => $service->summary($record)['target_date']?->format('d/m/Y') ?? '—'),
            ])
            ->defaultSort('nick')
            ->paginated(false);
    }
}
