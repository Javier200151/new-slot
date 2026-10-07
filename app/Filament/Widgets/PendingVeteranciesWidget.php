<?php

namespace App\Filament\Widgets;

use App\Models\User;
use App\Services\VeterancyAwardService;
use App\Services\VeterancyService;
use Filament\Actions\BulkAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;

class PendingVeteranciesWidget extends TableWidget
{
    protected int|string|array $columnSpan = 'full';

    protected static ?int $sort = 4;

    public static function canView(): bool
    {
        return (bool) auth()->user()?->can('veterancy-settings.update');
    }

    public function table(Table $table): Table
    {
        $service = app(VeterancyService::class);
        $ids = $service->pendingUsers()->pluck('id')->all();

        return $table
            ->heading('Veteranías pendientes de aprobar')
            ->description('Selecciona una o varias personas. La aprobación entrega la metopa configurada y crea un único post para todo el lote.')
            ->query(fn (): Builder => User::query()
                ->whereKey($ids)
                ->with(['status', 'metopas']))
            ->columns([
                TextColumn::make('nick')->label('Miembro')->sortable(),
                TextColumn::make('veterancy_level')
                    ->label('Veteranía')
                    ->state(fn (User $record): string => $service->levelLabel($service->summary($record)['pending_level']))
                    ->badge(),
                TextColumn::make('effective_days')
                    ->label('Tiempo ACTIVO')
                    ->state(fn (User $record): string => number_format((int) $service->summary($record)['effective_days'], 0, ',', '.') . ' días'),
            ])
            ->toolbarActions([
                BulkAction::make('approveVeterancies')
                    ->label('Aprobar seleccionados')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Aprobar veteranías seleccionadas')
                    ->modalDescription('Se asignarán las metopas correspondientes y se publicará un único hilo en la subcategoría configurada.')
                    ->deselectRecordsAfterCompletion()
                    ->action(function (Collection $records, VeterancyAwardService $awards): void {
                        try {
                            $result = $awards->approve($records);
                        } catch (Throwable $exception) {
                            report($exception);

                            Notification::make()
                                ->danger()
                                ->title('No se pudieron aprobar las veteranías')
                                ->body($exception->getMessage())
                                ->send();

                            return;
                        }

                        $this->resetTable();

                        Notification::make()
                            ->success()
                            ->title('Veteranías aprobadas')
                            ->body($result['awarded'] > 0
                                ? $result['awarded'] . ' veteranía(s) procesada(s) y publicación creada.'
                                : 'Las selecciones ya estaban procesadas o dejaron de estar pendientes.')
                            ->send();

                        if (filled($result['telegram_error'] ?? null)) {
                            Notification::make()
                                ->warning()
                                ->title('Veteranías guardadas, pero Telegram falló')
                                ->body((string) $result['telegram_error'])
                                ->persistent()
                                ->send();
                        }
                    }),
            ])
            ->paginated(false);
    }
}
