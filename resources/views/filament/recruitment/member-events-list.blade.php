@php
    // Los mismos eventos, roles, escuadras y enlaces que muestra el historial de reclutas.
    $items = $events->map(function (array $item): array {
        $event = $item['event'];
        $type = $event->activity?->activityType;

        return [
            'id' => (int) $event->id,
            'title' => $event->name ?: $event->activity?->name ?: 'Evento #' . $event->id,
            'day' => $event->date?->format('d/m/Y') ?? '—',
            'time' => $event->date?->format('H:i') ?? '',
            'timestamp' => $event->date?->timestamp ?? 0,
            'year' => (int) ($event->date?->format('Y') ?? 0),
            'month' => (int) ($event->date?->format('n') ?? 0),
            'activityTypeId' => $type ? (string) $type->id : '',
            'activityType' => $type?->name ?? '',
            'squad' => $item['squad'],
            'role' => $item['role'],
            'url' => route('events.show', $event) . '#orbat',
        ];
    })->values();

    $years = $items->pluck('year')->filter()->unique()->sortDesc()->values();
    $latestYear = $years->first() ?? (int) now()->year;
    $latestMonth = $items->first()['month'] ?? (int) now()->month;
    $types = $items->filter(fn (array $item) => $item['activityTypeId'] !== '')
        ->unique('activityTypeId')
        ->sortBy('activityType')
        ->values();
    $hasMissingType = $items->contains(fn (array $item) => $item['activityTypeId'] === '');
@endphp

<div class="recruitment-events-modal member-events-modal"
    x-data="{
        events: {{ \Illuminate\Support\Js::from($items) }},
        period: 'all',
        year: '{{ $latestYear }}',
        month: '{{ $latestMonth }}',
        activityType: 'all',
        sinceYear: {{ now()->subYear()->timestamp }},
        sinceMonth: {{ now()->subDays(30)->timestamp }},
        get filteredEvents() {
            return this.events.filter((event) => {
                if (this.period === 'last_year' && event.timestamp < this.sinceYear) return false;
                if (this.period === 'last_month' && event.timestamp < this.sinceMonth) return false;
                if (this.period === 'year' && String(event.year) !== String(this.year)) return false;
                if (this.period === 'month' && (String(event.year) !== String(this.year) || String(event.month) !== String(this.month))) return false;
                if (this.activityType !== 'all' && (this.activityType === 'none'
                    ? event.activityTypeId !== ''
                    : event.activityTypeId !== this.activityType)) return false;
                return true;
            });
        }
    }">
    <div class="member-events-modal__filters">
        <label>
            <span>Periodo</span>
            <select x-model="period">
                <option value="all">Todo el historial</option>
                <option value="last_year">Últimos 12 meses</option>
                <option value="last_month">Últimos 30 días</option>
                <option value="year">Por año</option>
                <option value="month">Por mes</option>
            </select>
        </label>
        <label x-show="period === 'year' || period === 'month'" x-cloak>
            <span>Año</span>
            <select x-model="year">
                @foreach ($years as $year)
                    <option value="{{ $year }}">{{ $year }}</option>
                @endforeach
            </select>
        </label>
        <label x-show="period === 'month'" x-cloak>
            <span>Mes</span>
            <select x-model="month">
                @foreach (['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'] as $index => $name)
                    <option value="{{ $index + 1 }}">{{ $name }}</option>
                @endforeach
            </select>
        </label>
        <label>
            <span>Tipo de actividad</span>
            <select x-model="activityType">
                <option value="all">Todos los tipos</option>
                @foreach ($types as $type)
                    <option value="{{ $type['activityTypeId'] }}">{{ $type['activityType'] }}</option>
                @endforeach
                @if ($hasMissingType)
                    <option value="none">Sin tipo</option>
                @endif
            </select>
        </label>
    </div>

    <div class="recruitment-events-modal__summary" aria-live="polite">
        <strong x-text="filteredEvents.length">{{ $items->count() }}</strong>
        <span x-text="filteredEvents.length === 1 ? 'evento jugado con estos filtros.' : 'eventos jugados con estos filtros.'">eventos jugados con estos filtros.</span>
    </div>

    <div class="recruitment-events-modal__list member-events-modal__list" x-show="filteredEvents.length > 0">
        <template x-for="event in filteredEvents" :key="event.id">
            <a :href="event.url" target="_blank" rel="noopener noreferrer" class="recruitment-events-modal__row">
                <div class="recruitment-events-modal__date">
                    <span class="recruitment-events-modal__day" x-text="event.day"></span>
                    <span class="recruitment-events-modal__time" x-text="event.time"></span>
                </div>
                <div class="recruitment-events-modal__main">
                    <div class="recruitment-events-modal__title" x-text="event.title"></div>
                    <div class="recruitment-events-modal__meta">
                        <span x-show="event.activityType !== ''" x-text="event.activityType"></span>
                        <span class="recruitment-events-modal__squad" x-text="'Escuadra: ' + event.squad"></span>
                        <span class="recruitment-events-modal__role" x-text="event.role"></span>
                    </div>
                </div>
                <div class="recruitment-events-modal__arrow" aria-hidden="true">↗</div>
            </a>
        </template>
    </div>
    <div class="recruitment-events-modal__empty" x-show="filteredEvents.length === 0" x-cloak>
        No hay eventos jugados que coincidan con estos filtros.
    </div>
</div>
