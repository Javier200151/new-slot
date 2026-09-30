<div class="recruitment-events-modal">
    @if ($events->isNotEmpty())
        <div class="recruitment-events-modal__summary">
            <strong>{{ $events->count() }}</strong>
            {{ \Illuminate\Support\Str::plural('evento jugado', $events->count()) }} durante este periodo.
        </div>

        <div class="recruitment-events-modal__list">
            @foreach ($events as $item)
                @php($event = $item['event'])
                <a
                    href="{{ route('events.show', $event) }}#orbat"
                    target="_blank"
                    rel="noopener noreferrer"
                    class="recruitment-events-modal__row"
                >
                    <div class="recruitment-events-modal__date">
                        <span class="recruitment-events-modal__day">{{ $event->date?->format('d/m/Y') ?? '—' }}</span>
                        <span class="recruitment-events-modal__time">{{ $event->date?->format('H:i') ?? '' }}</span>
                    </div>

                    <div class="recruitment-events-modal__main">
                        <div class="recruitment-events-modal__title">
                            {{ $event->name ?: $event->activity?->name ?: 'Evento #' . $event->id }}
                        </div>

                        <div class="recruitment-events-modal__meta">
                            @if ($event->activity?->activityType?->name)
                                <span>{{ $event->activity->activityType->name }}</span>
                            @endif

                            <span class="recruitment-events-modal__squad">
                                Escuadra: {{ $item['squad'] }}
                            </span>

                            <span class="recruitment-events-modal__role">{{ $item['role'] }}</span>
                        </div>
                    </div>

                    <div class="recruitment-events-modal__arrow" aria-hidden="true">↗</div>
                </a>
            @endforeach
        </div>
    @else
        <div class="recruitment-events-modal__empty">
            No constan eventos jugados durante este periodo.
        </div>
    @endif
</div>
