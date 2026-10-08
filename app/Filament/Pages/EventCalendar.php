<?php

namespace App\Filament\Pages;

use App\Models\EventCalendarReservation;
use App\Models\MemberProcedureSetting;
use App\Models\User;
use App\Services\EventCalendarDataService;
use App\Services\MemberProcedures\WeeklyActivityTelegramService;
use BackedEnum;
use Carbon\CarbonImmutable;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Throwable;
use UnitEnum;

class EventCalendar extends Page
{
    protected static string|BackedEnum|null $navigationIcon =
        Heroicon::OutlinedCalendarDays;

    protected static string|UnitEnum|null $navigationGroup = 'Eventos';

    protected static ?int $navigationSort = 1;

    protected static ?string $navigationLabel = 'Calendario';

    protected static ?string $title = 'Calendario de eventos';

    protected string $view = 'filament.pages.event-calendar';

    public int $month;

    public int $year;

    public ?string $selectedDate = null;

    public string $comment = '';

    public ?int $assigneeUserId = null;

    public ?int $editingReservationId = null;

    public bool $weeklyActivityPanelOpen = false;

    /** @var array<string, mixed> */
    public array $weeklyActivityPreview = [];

    public static function canAccess(): bool
    {
        $user = Auth::user();

        return $user?->hasRole('admin')
            || ($user?->can('event-calendar.view') ?? false)
            || ($user?->can('event-calendar.reserve') ?? false)
            || ($user?->can('event-calendar.manage') ?? false);
    }

    public function mount(): void
    {
        abort_unless(static::canAccess(), 403);

        $today = CarbonImmutable::today();
        $month = (int) request()->query('month', $today->month);
        $year = (int) request()->query('year', $today->year);

        $this->month = min(12, max(1, $month));
        $this->year = min(2100, max(2000, $year));
    }

    public function openReservation(string $date, ?int $reservationId = null): void
    {
        abort_unless($this->canReserve() || $this->canManage(), 403);

        $selected = CarbonImmutable::createFromFormat('!Y-m-d', $date);
        abort_unless($selected && $selected->format('Y-m-d') === $date, 422);

        $reservation = $reservationId
            ? EventCalendarReservation::query()->findOrFail($reservationId)
            : null;

        if ($reservation) {
            abort_unless($this->canManage(), 403);
            abort_unless($reservation->reserved_date->toDateString() === $date, 404);
        } else {
            abort_if(
                EventCalendarReservation::query()
                    ->whereDate('reserved_date', $date)
                    ->exists(),
                409,
            );
        }

        $this->selectedDate = $date;
        $this->editingReservationId = $reservation?->id;
        $this->comment = $reservation?->comment ?? '';
        $this->assigneeUserId = $reservation?->user_id ?: Auth::id();
        $this->resetValidation();
    }

    public function cancelReservation(): void
    {
        $this->resetReservationForm();
    }

    public function saveReservation(): void
    {
        abort_unless($this->canReserve() || $this->canManage(), 403);

        $validated = $this->validate([
            'selectedDate' => ['required', 'date_format:Y-m-d'],
            'comment' => ['required', 'string', 'min:2', 'max:120'],
            'assigneeUserId' => [
                Rule::requiredIf(fn (): bool => $this->canManage()),
                'nullable',
                'integer',
                Rule::exists('users', 'id')->whereNull('deleted_at'),
            ],
        ], [
            'comment.required' => 'Escribe un comentario corto para la reserva.',
            'comment.max' => 'El comentario no puede superar los 120 caracteres.',
            'assigneeUserId.required' => 'Selecciona el jugador al que asignas la reserva.',
        ]);

        $userId = $this->canManage()
            ? (int) $validated['assigneeUserId']
            : (int) Auth::id();
        $assignee = User::query()->findOrFail($userId);

        DB::transaction(function () use ($validated, $assignee): void {
            $reservation = $this->editingReservationId
                ? EventCalendarReservation::query()
                    ->lockForUpdate()
                    ->findOrFail($this->editingReservationId)
                : null;

            if ($reservation) {
                abort_unless($this->canManage(), 403);
                abort_unless(
                    $reservation->reserved_date->toDateString() === $validated['selectedDate'],
                    404,
                );
            } elseif (EventCalendarReservation::query()
                ->whereDate('reserved_date', $validated['selectedDate'])
                ->lockForUpdate()
                ->exists()) {
                throw ValidationException::withMessages([
                    'selectedDate' => 'Esa fecha acaba de ser reservada por otro jugador.',
                ]);
            }

            $payload = [
                'reserved_date' => $validated['selectedDate'],
                'user_id' => $assignee->id,
                'reserved_for_nick' => $assignee->nick,
                'comment' => trim($validated['comment']),
            ];

            if ($reservation) {
                $reservation->update($payload);
            } else {
                EventCalendarReservation::create($payload);
            }
        });

        $this->resetReservationForm();

        Notification::make()
            ->success()
            ->title('Reserva guardada')
            ->body('La fecha ha quedado reservada en el calendario.')
            ->send();
    }

    public function deleteReservation(int $reservationId): void
    {
        abort_unless($this->canManage(), 403);

        EventCalendarReservation::query()->findOrFail($reservationId)->delete();
        $this->resetReservationForm();

        Notification::make()
            ->success()
            ->title('Reserva eliminada')
            ->send();
    }

    /** @return array<string, mixed> */
    public function weeklyActivityWindow(): array
    {
        return app(WeeklyActivityTelegramService::class)->window();
    }

    public function prepareWeeklyActivityTelegram(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $setting = MemberProcedureSetting::current();
            $preview = app(WeeklyActivityTelegramService::class)->preview($setting);
        } catch (Throwable $exception) {
            report($exception);
            $this->notifyError($exception->getMessage());
            return;
        }

        if (! ($preview['window_open'] ?? false)) {
            $this->notifyError('La actividad semanal solo puede enviarse desde el domingo a las 00:00 hasta el lunes a las 18:00.');
            return;
        }

        $this->weeklyActivityPreview = $preview;
        $this->weeklyActivityPanelOpen = true;
    }

    public function closeWeeklyActivityTelegram(): void
    {
        $this->weeklyActivityPanelOpen = false;
        $this->weeklyActivityPreview = [];
    }

    public function sendWeeklyActivityTelegram(): void
    {
        abort_unless(static::canAccess(), 403);

        try {
            $setting = MemberProcedureSetting::current();
            $preferredEnding = array_key_exists('ending', $this->weeklyActivityPreview)
                ? (string) $this->weeklyActivityPreview['ending']
                : null;

            // Revalidamos justo antes de enviar por si el calendario ha cambiado
            // desde que se abrió la revisión.
            $freshPreview = app(WeeklyActivityTelegramService::class)->preview($setting, null, $preferredEnding);
            $this->weeklyActivityPreview = $freshPreview;

            if (! ($freshPreview['can_send'] ?? false)) {
                Notification::make()
                    ->danger()
                    ->title('No se puede enviar la actividad semanal')
                    ->body(implode("\n", (array) ($freshPreview['blocking_errors'] ?? [])))
                    ->persistent()
                    ->send();
                return;
            }

            $result = app(WeeklyActivityTelegramService::class)->send($setting, null, $preferredEnding);
        } catch (Throwable $exception) {
            report($exception);
            Notification::make()
                ->danger()
                ->title('No se pudo enviar la actividad semanal')
                ->body($exception->getMessage())
                ->persistent()
                ->send();
            return;
        }

        $this->weeklyActivityPanelOpen = false;
        $this->weeklyActivityPreview = [];

        Notification::make()
            ->success()
            ->title('Actividad semanal enviada a Telegram')
            ->body('Mensaje publicado en = ALPHA FORCE NETWORK =' . (filled($result['message_id'] ?? null) ? ' · mensaje #' . $result['message_id'] : '') . '.')
            ->send();
    }

    public function getViewData(): array
    {
        $monthStart = CarbonImmutable::create($this->year, $this->month)->startOfMonth();
        $calendar = app(EventCalendarDataService::class)->month($monthStart);

        return [
            'calendarDays' => $calendar['days'],
            'monthName' => $calendar['month_name'],
            'previousMonthUrl' => static::getUrl([
                'month' => $monthStart->subMonth()->month,
                'year' => $monthStart->subMonth()->year,
            ]),
            'nextMonthUrl' => static::getUrl([
                'month' => $monthStart->addMonth()->month,
                'year' => $monthStart->addMonth()->year,
            ]),
            'canReserve' => $this->canReserve(),
            'canManage' => $this->canManage(),
            'assignableUsers' => $this->canManage()
                ? User::query()
                    ->whereHas('status', fn ($query) => $query
                        ->whereIn('name', ['ACTIVO', 'RESERVA', 'RECLUTA']))
                    ->orderBy('nick')
                    ->get(['id', 'nick'])
                : collect(),
        ];
    }

    private function canReserve(): bool
    {
        $user = Auth::user();

        return $user?->hasRole('admin')
            || ($user?->can('event-calendar.reserve') ?? false);
    }

    private function canManage(): bool
    {
        $user = Auth::user();

        return $user?->hasRole('admin')
            || ($user?->can('event-calendar.manage') ?? false);
    }

    private function notifyError(string $message): void
    {
        Notification::make()
            ->danger()
            ->title('No se pudo completar la acción')
            ->body($message)
            ->send();
    }

    private function resetReservationForm(): void
    {
        $this->selectedDate = null;
        $this->editingReservationId = null;
        $this->comment = '';
        $this->assigneeUserId = null;
        $this->resetValidation();
    }
}
