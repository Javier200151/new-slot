<?php

namespace App\Services;

use App\Models\Event;
use App\Models\EventCalendarReservation;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

class EventCalendarDataService
{
    /**
     * Fuente compartida del calendario administrativo y de su vista compacta
     * en el dashboard. No contiene acciones de edición/reserva.
     *
     * @return array{
     *     month_start: CarbonImmutable,
     *     month_name: string,
     *     label: string,
     *     days: Collection<int, array<string, mixed>>,
     *     weeks: array<int, array<int, array<string, mixed>>>
     * }
     */
    public function month(CarbonInterface $month): array
    {
        $monthStart = CarbonImmutable::instance($month)->startOfMonth();
        $monthEnd = $monthStart->endOfMonth();
        $calendarStart = $monthStart->startOfWeek(CarbonInterface::MONDAY);
        $calendarEnd = $monthEnd->endOfWeek(CarbonInterface::SUNDAY);

        $eventsByDate = Event::query()
            ->whereHas('eventStatus', fn ($query) => $query
                ->whereIn('name', ['ACTIVO', 'FINALIZADO', 'CANCELADO', 'BORRADOR']))
            ->whereBetween('date', [$calendarStart->startOfDay(), $calendarEnd->endOfDay()])
            ->with(['eventStatus', 'activity.activityType'])
            ->orderBy('date')
            ->get()
            ->groupBy(fn (Event $event): string => $event->date->toDateString());

        $reservationsByDate = EventCalendarReservation::query()
            ->whereBetween('reserved_date', [$calendarStart->toDateString(), $calendarEnd->toDateString()])
            ->with('user.status')
            ->get()
            ->keyBy(fn (EventCalendarReservation $reservation): string => $reservation->reserved_date->toDateString());

        $days = collect();
        for ($day = $calendarStart; $day->lte($calendarEnd); $day = $day->addDay()) {
            $date = $day->toDateString();
            $days->push([
                'date' => $day,
                'is_current_month' => $day->month === $monthStart->month,
                'is_today' => $day->isToday(),
                'events' => $eventsByDate->get($date, collect()),
                'reservation' => $reservationsByDate->get($date),
            ]);
        }

        $monthNames = [
            1 => 'Enero', 2 => 'Febrero', 3 => 'Marzo', 4 => 'Abril',
            5 => 'Mayo', 6 => 'Junio', 7 => 'Julio', 8 => 'Agosto',
            9 => 'Septiembre', 10 => 'Octubre', 11 => 'Noviembre', 12 => 'Diciembre',
        ];

        return [
            'month_start' => $monthStart,
            'month_name' => $monthNames[$monthStart->month],
            'label' => $monthNames[$monthStart->month] . ' ' . $monthStart->year,
            'days' => $days,
            'weeks' => $days->chunk(7)->map(fn (Collection $week): array => $week->values()->all())->values()->all(),
        ];
    }
}
