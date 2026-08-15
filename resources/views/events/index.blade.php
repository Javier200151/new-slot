@extends('layouts.metopas')

@section('title', 'Eventos')

@section('meta-description', 'Calendario de eventos y operativos de Squad ALPHA.')

@section('body-class', 'events-body')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/events.css') }}">
@endpush

@section('content')
    <section class="events-hero">
        <div class="container">
            {{-- <span class="events-kicker">Agenda operativa</span> --}}
            <h1>Eventos</h1>
            <p>Consulta los próximos operativos y el histórico de eventos finalizados.</p>
        </div>
    </section>

    <section class="events-calendar-section" aria-labelledby="calendar-title">
        <div class="container">
            <header class="events-calendar-header">
                <div>
                    <span>Calendario</span>
                    <h2 id="calendar-title">{{ $monthName }} {{ $year }}</h2>
                </div>

                <div class="events-calendar-controls">
                    <a
                        href="{{ route('events.index', array_filter([
                            'month' => $previousMonth->month,
                            'year' => $previousMonth->year,
                            'type' => $selectedTypeId,
                        ])) }}"
                        class="events-calendar-arrow"
                        aria-label="Mes anterior"
                    >
                        ←
                    </a>

                    <form method="GET" action="{{ route('events.index') }}" class="events-month-form">
                        @if($selectedTypeId)
                            <input type="hidden" name="type" value="{{ $selectedTypeId }}">
                        @endif

                        <label>
                            <span class="sr-only">Mes</span>
                            <select name="month">
                                @foreach($monthNames as $monthNumber => $availableMonthName)
                                    <option value="{{ $monthNumber }}" @selected($monthNumber === $month)>
                                        {{ $availableMonthName }}
                                    </option>
                                @endforeach
                            </select>
                        </label>

                        <label>
                            <span class="sr-only">Año</span>
                            <input type="number" name="year" value="{{ $year }}" min="2000" max="2100">
                        </label>

                        <button type="submit">Ir</button>
                    </form>

                    <a
                        href="{{ route('events.index', array_filter([
                            'month' => $nextMonth->month,
                            'year' => $nextMonth->year,
                            'type' => $selectedTypeId,
                        ])) }}"
                        class="events-calendar-arrow"
                        aria-label="Mes siguiente"
                    >
                        →
                    </a>
                </div>
            </header>

            <div class="events-calendar-scroll">
                <div class="events-calendar">
                    @foreach(['Lun', 'Mar', 'Mié', 'Jue', 'Vie', 'Sáb', 'Dom'] as $weekday)
                        <div class="events-calendar__weekday">{{ $weekday }}</div>
                    @endforeach

                    @foreach($calendarDays as $calendarDay)
                        <div @class([
                            'events-calendar__day',
                            'is-outside' => ! $calendarDay['is_current_month'],
                            'is-today' => $calendarDay['is_today'],
                        ])>
                            <time datetime="{{ $calendarDay['date']->toDateString() }}">
                                {{ $calendarDay['date']->day }}
                            </time>

                            @if($calendarDay['is_current_month'])
                                <div class="events-calendar__items">
                                    @foreach($calendarDay['events'] as $event)
                                        <a
                                            href="{{ route('events.show', $event) }}"
                                            class="events-calendar__event"
                                            title="{{ $event->name ?: $event->operation?->name }}"
                                            @style([
                                                '--event-color: ' . ($event->operation?->operationType?->color ?? '') => filled($event->operation?->operationType?->color),
                                            ])
                                        >
                                            @if($event->operation?->period?->ico || $event->operation?->platform?->image)
                                                <span class="events-calendar__icons" aria-hidden="true">
                                                    @if($event->operation?->period?->ico)
                                                        <img src="{{ asset('storage/' . $event->operation->period->ico) }}" alt="">
                                                    @endif

                                                    @if($event->operation?->platform?->image)
                                                        <img src="{{ asset('storage/' . $event->operation->platform->image) }}" alt="">
                                                    @endif
                                                </span>
                                            @endif

                                            <span class="events-calendar__event-name">{{ $event->name ?: $event->operation?->name }}</span>
                                        </a>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    </section>

    <section class="events-list-section" aria-labelledby="events-list-title">
        <div class="container">
            <header class="events-list-header">
                <div>
                    <span>Eventos del mes</span>
                    <h2 id="events-list-title">{{ $listedEvents->count() }} {{ $listedEvents->count() === 1 ? 'evento encontrado' : 'eventos encontrados' }}</h2>
                </div>

                <form method="GET" action="{{ route('events.index') }}" class="events-filters">
                    <input type="hidden" name="month" value="{{ $month }}">
                    <input type="hidden" name="year" value="{{ $year }}">

                    <label>
                        <span>Tipo de evento</span>
                        <select name="type">
                            <option value="">Todos los tipos</option>
                            @foreach($operationTypes as $operationType)
                                <option value="{{ $operationType->id }}" @selected($selectedTypeId === $operationType->id)>
                                    {{ $operationType->name }}
                                </option>
                            @endforeach
                        </select>
                    </label>

                    <label>
                        <span>Fecha inicial</span>
                        <input type="date" name="date_from" value="{{ $selectedDateFrom }}">
                    </label>

                    <label>
                        <span>Fecha final</span>
                        <input type="date" name="date_to" value="{{ $selectedDateTo }}">
                    </label>

                    <button type="submit" class="btn btn-primary">Filtrar</button>

                    @if($hasListFilters)
                        <a href="{{ route('events.index', ['month' => $month, 'year' => $year]) }}" class="events-filters__clear">
                            Limpiar
                        </a>
                    @endif
                </form>
            </header>

            @if($listedEvents->isEmpty())
                <div class="events-empty">
                    <strong>Sin eventos</strong>
                    <p>No hay eventos que coincidan con los filtros seleccionados.</p>
                </div>
            @else
                <div class="events-list">
                    @foreach($listedEvents as $event)
                        @include('events.partials.card', ['event' => $event])
                    @endforeach
                </div>
            @endif
        </div>
    </section>
@endsection
